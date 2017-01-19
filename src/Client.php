<?php
namespace Phasty\ServiceClient {
    use \Phasty\Stream\StreamSet;
    use \Phasty\Stream\Stream;

    abstract class Client {

        private function __construct() {}

        public static function instance() {
            static $instance = null;
            if (!isset($instance)) {
                $instance = new static;
            }
            return $instance;
        }

        protected function encodeArguments($arguments) {
            return json_encode($arguments);
        }

        private function getPort($uriInfo) {
            return ":" . (empty($uriInfo["port"]) ? 80 : $uriInfo["port"]);
        }

        private function buildRequest($data) {
            return
                "POST " . $data[ "path" ] . " HTTP/1.0\n" .
                "HOST: " . $data[ "host" ] . $this->getPort($data) . "\n" .
                "Content-Type: application/json\n" .
                "Content-Length: " . strlen($data[ "body" ]) . "\n\n" .
                $data[ "body" ];
        }

        private function getSocket($uriInfo) {
            $socket = stream_socket_client("tcp://" . $uriInfo[ "host" ] . $this->getPort($uriInfo), $errno, $errstr, 1, STREAM_CLIENT_ASYNC_CONNECT);//STREAM_CLIENT_PERSISTENT

            stream_set_blocking($socket, 1);
            return $socket;
        }

        public function post($uri, $arguments) {
            $fullUri   = $this->getServiceUri() . $uri;
            $parsedUri = parse_url($fullUri);

            set_error_handler(function($errno, $error) {
                throw new \Exception($error);
            });

            try {
                $body    = $this->encodeArguments($arguments);
                $request = $this->buildRequest($parsedUri + compact("body"));
                $socket  = $this->getSocket($parsedUri);

                fwrite($socket, $request);

                restore_error_handler();
            } catch (\Exception $e) {
                restore_error_handler();
                if (!empty($socket)) {
                    fclose($socket);
                }
                return new Result($e);
            }

            return new Result(new Stream($socket));
        }

        public static function waitAllServices() {
            \Phasty\Stream\StreamSet::instance()->listen();
        }

        protected function getServiceUri() {
            throw new \Exception("Service URI not configured");
        }

        public static function isWaitingForServices() {
            return \Phasty\Stream\StreamSet::instance()->getReadStreamsCount() > 0;
        }
    }
}
