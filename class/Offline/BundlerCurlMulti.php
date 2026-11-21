<?php
namespace GT\Website\Offline;

use GT\Curl\CurlMulti;

/** Releases each completed Fetch transfer's native resources immediately. */
class BundlerCurlMulti extends CurlMulti {
	public function exec(int &$stillRunning):int {
		if(!isset($this->mh)) {
			$stillRunning = 0;
			return CURLM_OK;
		}
		$status = parent::exec($stillRunning);
		if($status === CURLM_OK && $stillRunning === 0) $this->close();
		return $status;
	}

	public function close():void {
		if(!isset($this->mh)) return;
		foreach($this->curlHandleArray as $handle) {
			curl_multi_remove_handle($this->mh, $handle);
			// Break callbacks retaining Fetch's resolver and its completed jobs.
			curl_setopt($handle, CURLOPT_HEADERFUNCTION, null);
			curl_setopt($handle, CURLOPT_WRITEFUNCTION, null);
			curl_setopt($handle, CURLOPT_PROGRESSFUNCTION, null);
		}
		$this->curlHandleArray = [];
		$this->responseBodyArray = [];
		// curl_multi_close alone does not free the PHP 8 native handle.
		unset($this->mh);
	}
}
