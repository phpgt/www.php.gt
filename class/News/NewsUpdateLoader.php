<?php
namespace GT\Website\News;

use DateTime;
use Gt\Fetch\Http;
use Gt\FileCache\Cache;
use RuntimeException;

class NewsUpdateLoader {
	private const GITHUB_API_ACCEPT = "application/vnd.github+json";
	private const GITHUB_API_USER_AGENT = "www.php.gt website";
	private const GITHUB_ORG = "phpgt";
	private const GITHUB_DISCUSSION_REPO = "WebEngine";
	private const GITHUB_ANNOUNCEMENT_CATEGORY = "announcements";

	public function __construct(
		private readonly Cache $cache,
		private readonly Http $http,
		private readonly string $githubToken,
	) {
	}

	/** @return array<Release> */
	public function loadReleaseList():array {
		$releaseList = [];

		foreach($this->getContentRepoList() as $repo) {
			$json = $this->cache->getString(
				"release-$repo",
				fn() => $this->fetchGithubRestJson(
					"https://api.github.com/repos/" . self::GITHUB_ORG . "/$repo/releases?per_page=100&page=1",
				)
			);
			$releaseData = json_decode($json, true);

			foreach($releaseData as $release) {
				$releaseList[] = new Release(
					$repo,
					new DateTime($release["published_at"]),
					$release["tag_name"],
					$release["name"],
					$release["body"],
				);
			}
		}

		return $releaseList;
	}

	/** @return array<Announcement> */
	public function loadAnnouncementList():array {
		$announcementCategoryId = $this->getAnnouncementCategoryId();
		$announcementList = [];
		$page = 1;
		$cursor = null;

		do {
			$json = $this->cache->getString(
				"discussion-announcements-$page",
				fn() => $this->fetchGithubGraphql(
					<<<'GRAPHQL'
query($categoryId: ID!, $cursor: String) {
  repository(owner: "phpgt", name: "WebEngine") {
    discussions(
      first: 100
      after: $cursor
      categoryId: $categoryId
      orderBy: {field: CREATED_AT, direction: DESC}
    ) {
      nodes {
        number
        title
        body
        createdAt
        author {
          login
          ... on User {
            name
          }
        }
      }
      pageInfo {
        hasNextPage
        endCursor
      }
    }
  }
}
GRAPHQL,
					[
						"categoryId" => $announcementCategoryId,
						"cursor" => $cursor,
					],
				)
			);

			$discussionResponse = json_decode($json, true);
			$discussionConnection = $discussionResponse["data"]["repository"]["discussions"] ?? null;
			if(!$discussionConnection) {
				break;
			}

			foreach($discussionConnection["nodes"] as $discussion) {
				array_push($announcementList, new Announcement(
					$discussion["number"],
					$discussion["title"],
					$discussion["body"],
					new DateTime($discussion["createdAt"]),
					$this->formatAuthor(
						$discussion["author"]["login"] ?? null,
						$discussion["author"]["name"] ?? null
					),
				));
			}

			$cursor = $discussionConnection["pageInfo"]["endCursor"] ?? null;
			$page++;
		}
		while(($discussionConnection["pageInfo"]["hasNextPage"] ?? false) && $cursor);

		return $announcementList;
	}

	/** @return array<string> */
	private function getContentRepoList():array {
		$repoList = [];
		foreach(glob("data/content/*/") as $dir) {
			if($dir[0] === ".") {
				continue;
			}

			$repoList[] = pathinfo($dir, PATHINFO_BASENAME);
		}

		return $repoList;
	}

	private function getAnnouncementCategoryId():string {
		$json = $this->cache->getString(
			"discussion-categories-" . self::GITHUB_DISCUSSION_REPO,
			fn() => $this->fetchGithubGraphql(
				<<<'GRAPHQL'
query {
  repository(owner: "phpgt", name: "WebEngine") {
    discussionCategories(first: 20) {
      nodes {
        id
        slug
      }
    }
  }
}
GRAPHQL
			)
		);

		$categoryResponse = json_decode($json, true);
		$categoryList = $categoryResponse["data"]["repository"]["discussionCategories"]["nodes"] ?? [];
		foreach($categoryList as $category) {
			if(($category["slug"] ?? null) === self::GITHUB_ANNOUNCEMENT_CATEGORY) {
				return $category["id"];
			}
		}

		throw new RuntimeException("Announcement discussion category not found.");
	}

	/** @param array<string, mixed> $variables */
	private function fetchGithubGraphql(
		string $query,
		array $variables = [],
	):string {
		$response = $this->http->awaitFetch("https://api.github.com/graphql", [
			"method" => "POST",
			"body" => json_encode([
				"query" => $query,
				"variables" => $variables,
			]),
			"headers" => $this->getGithubHeaders([
				"Content-Type" => "application/json",
			]),
		]);
		return $response->awaitText();
	}

	private function fetchGithubRestJson(string $url):string {
		$response = $this->http->awaitFetch($url, [
			"headers" => $this->getGithubHeaders(),
		]);
		return $response->awaitText();
	}

	private function formatAuthor(?string $login, ?string $name):?string {
		if(!$login) {
			return null;
		}

		if(!$name) {
			return $login;
		}

		return "$login:$name";
	}

	/** @param array<string, string> $additionalHeaders */
	private function getGithubHeaders(array $additionalHeaders = []):array {
		return [
			"Authorization" => "Bearer $this->githubToken",
			"Accept" => self::GITHUB_API_ACCEPT,
			"User-Agent" => self::GITHUB_API_USER_AGENT,
		] + $additionalHeaders;
	}

	/** @return array<NewsUpdateItem> */
	public function sort(NewsUpdateItem...$items):array {
		usort(
			$items,
			fn(NewsUpdateItem $a, NewsUpdateItem $b) => $b->getDateTime() <=> $a->getDateTime()
		);
		return $items;
	}
}
