<?php

    namespace App\Http\Controllers\components;

    use Illuminate\Support\Facades\Log;

    /**
     * Class CurlTransport
     * @var string $errorNo
     * @var string $refresh_token
     * @var integer $responseCode
     * @package App\Http\Controllers\amoCrm\components
     */
    class CurlTransport
    {
        public $errorNo;
        public $errorMessage;
        public $responseCode;

        protected $curlHandle;
        protected $verbose = true;
        protected $verify_pear = false;
        protected $verify_host = 0;
        protected $timeout = 15;
        protected $connection_timeout = 15;

        public function __construct()
        {
            $this->curlHandle = curl_init();
        }

        public function __destruct()
        {
            $this->curlClose();
        }

        public function send(string $url, array $header, ?array $requestBody = null, ?string $method = null)
        {
            $response = '';

            $this->setCurlUrl($url);
            $this->curlInit();
            $this->setHeader($header);
            $this->setRequestContent($requestBody);
            $this->setCurlMethod($method);

            $out = curl_exec($this->curlHandle);
//            Log::debug('$out'.$out);
//            return $out;
            $this->errorNo = curl_errno($this->curlHandle);

            if (0 < $this->errorNo) {
                $this->errorMessage = curl_error($this->curlHandle);
            } else {
                $this->responseCode = (int)curl_getinfo($this->curlHandle, CURLINFO_RESPONSE_CODE);
            }

            if ($out) {
                $response = json_decode($out, true);
            }
            return $response;
        }

        protected function setCurlUrl(string $url): void {
            curl_setopt($this->curlHandle, CURLOPT_URL, $url);
        }

        protected function setHeader(array $headers): void {
            curl_setopt($this->curlHandle, CURLOPT_HTTPHEADER, $headers);
        }

        protected function setCurlMethod($method): void {
            if ($method) {
                curl_setopt($this->curlHandle, CURLOPT_CUSTOMREQUEST, $method);
            }
        }

        protected function setRequestContent(?array $requestBody): void {
            if ($requestBody) {
                curl_setopt($this->curlHandle, CURLOPT_POSTFIELDS, json_encode($requestBody));
            }
        }

        protected function curlClose(): void {
            if (!empty($this->curlHandle)) {
                curl_close($this->curlHandle);
            }
        }

        protected function curlInit()
        {
            curl_setopt_array($this->curlHandle, [
                CURLOPT_VERBOSE => $this->verbose,

                CURLOPT_CONNECTTIMEOUT => $this->connection_timeout,
                CURLOPT_TIMEOUT => $this->timeout,

                CURLOPT_SSL_VERIFYPEER => 1,
                CURLOPT_SSL_VERIFYHOST => 2,

                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => false,

                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_USERAGENT => 'amoCRM-oAuth-client/1.0',

            ]);
        }
    }
