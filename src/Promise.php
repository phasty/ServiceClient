<?php
namespace Phasty\ServiceClient {
    use \React\Promise\Deferred;
    use \Phasty\Stream\StreamSet;
    use \Phasty\Stream\Stream;
    use \Phasty\Stream\Timer;

    class Promise {
        static public function create($stream, $onResolve) {
            $deferred = new Deferred();
            if (!$stream instanceof Stream) {
                self::resolveWith($deferred, $stream, $onResolve);
            } else {
                self::setListenersToStream($stream, $deferred, $onResolve);
            }
            return $deferred->promise();
        }

        /**
         * Устанавливает зарезолвленное значение. Если значение является исключением
         *
         * @param Deferred $deferred
         * @param mixed    $result     Ответ асинхронной операции либо исключение
         * @param null|callable $onResolve  Функция-обработчик результата
         */
        public static function resolveWith($deferred, $result, $onResolve) {
            if (is_callable($onResolve) && !($result instanceof \Exception)) {
                try {
                    $result = call_user_func($onResolve, $result);
                } catch (\Exception $e) {
                    $result = $e;
                }
            }

            if ($result instanceof \Exception) {
                $deferred->reject($result);
            } else {
                $deferred->resolve($result);
            }
        }

        protected static function setListenersToStream(Stream $stream, $deferred, $onResolve) {
            $response = new \Phasty\Server\Http\Response();
            $response->setReadStream($stream);

            $response->on("read-complete", function($event, $response) use($deferred, $onResolve) {
                set_error_handler(function() {});
                $body = json_decode($response->getBody(), true);
                restore_error_handler();

                if (is_null($body)) {
                    $result = new \Exception("Service response is not json:\n " . $response->getBody());
                } elseif ($response->getCode() > 299) {
                    $result = new \Exception($body[ "message" ], $response->getCode());
                } else {
                    $result = $body[ "result" ];

                }

                Promise::resolveWith($deferred, $result, $onResolve);
            });

            $response->on("error", function($event) use($deferred) {
                $deferred->reject(new \Exception($event->getData()));
            });

            $streamSet = StreamSet::instance();
            $streamSet->addReadStream($stream);

            $timer = new Timer(
                Result::OPERATION_TIMEOUT_SECONDS,
                Result::OPERATION_TIMEOUT_MICROSECONDS,
                function() use ($stream, $deferred) {
                    $deferred->reject(new \Exception("Operation timed out"));
                    $stream->close();
                }
            );

            $streamSet->addTimer($timer);
        }
    }
}
