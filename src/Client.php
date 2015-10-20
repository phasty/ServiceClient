<?php
namespace Ru7733\ServiceClient {
    use \Phasty\Stream\StreamSet;
    use \Phasty\Stream\Stream;

    class Client {
        protected function encodeArguments($arguments) {
            return json_encode($arguments);
        }

        private function getPortString($uriInfo) {
            return !empty($uriInfo["port"]) ? ":" . $uriInfo["port"] : "";
        }

        private function getRequest($data) {
            return
                "GET " . $data[ "path" ] . "\n" .
                "HOST: " . $data[ "host" ] . $this->getPortString($data) . "\n" .
                "Content-Type: application/json\n" .
                "Content-Length: " . strlen($data[ "body" ]) . "\n\n" .
                $data[ "body" ];
        }

        private function getSocket($uriInfo) {
            $socket = stream_socket_client("tcp://" . $uriInfo[ "host" ] . $this->getPortString($uriInfo), $errno, $errstr, 1, STREAM_CLIENT_ASYNC_CONNECT);//STREAM_CLIENT_PERSISTENT

            stream_set_blocking($socket, 1);
            return $socket;
        }



        public function get($uri, $arguments) {
            $fullUri   = $this->getServiceUri() . $uri;
            $parsedUri = parse_url($fullUri);

            set_error_handler(function($errno, $error) {
                throw new \Exception($error);
            });

            try {
                $body    = $this->encodeArguments($arguments);
                $request = $this->getRequest($parsedUri + compact("body"));
                $socket  = $this->getSocket($parsedUri);

                fwrite($socket, $request);
            } catch (\Exception $e) {
                if (!empty($socket)) {
                    fclose($socket);
                }
                return $e;
            }
            restore_error_handler();

            return new Stream($socket);
        }

        public function waitAllServices() {
            \Phasty\Stream\StreamSet::instance()->listen();
        }

        protected function getServiceUri() {
            throw new Exception("Service URI not configured");
        }
    }
}
