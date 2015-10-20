<?php
namespace Phasty\ServiceClient {
    use \React\Promise\Deferred;
    use \Phasty\Stream\StreamSet;
    use \Phasty\Stream\Stream;
    use \Phasty\Stream\Timer;

    class Promise {
        static public function create($stream) {
            $deferred = new Deferred();
            if (!$stream instanceof Stream) {
                $deferred->reject($stream);
            } else {
                self::setListenersToStream($stream, $deferred);
            }
            return $deferred->promise();
        }

        static protected function setListenersToStream(Stream $stream, $deferred) {
            $response = new \Phasty\Server\Http\Response();
            $response->setReadStream($stream);

            $response->on("read-complete", function($event, $response) use($deferred) {
                $body = json_decode($response->getBody(), true);
                if ($response->getCode() > 299) {
                    $deferred->reject($body[ "message" ]);
                    return;
                }
                $deferred->resolve($body[ "result" ]);
            });

            $response->on("error", function($event) use($deferred) {
                $deferred->reject($event->getBody());
            });

            $streamSet = \Phasty\Stream\StreamSet::instance();
            $streamSet->addReadStream($stream);

            $timer = new Timer(20, 3000000, function() use ($stream, $deferred) {
                $deferred->reject(new \Exception("Operation timed out"));
                $stream->close();
            });

            $streamSet->addTimer($timer);
        }
    }
}
