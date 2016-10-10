<?php
namespace Phasty\ServiceClient {
    use \React\Promise\Deferred;
    use \Phasty\Stream\StreamSet;
    use \Phasty\Stream\Stream;
    use \Phasty\Stream\Timer;

    class Promise {
        public static function create($stream, $onResolve) {
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
         * результат промиса принимает состояние rejected.
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

        /**
         * Устанавливает обработчики событий для сокетов
         *
         * @param Stream   $stream
         * @param Deferred $deferred
         * @param callable $onResolve
         */
        protected static function setListenersToStream(Stream $stream, $deferred, $onResolve) {
            $response = new \Phasty\Server\Http\Response();
            $response->setReadStream($stream);

            $response->on("read-complete", function($event, $response) use($deferred, $onResolve) {
                $result = Result::processResponse($response);
                Promise::resolveWith($deferred, $result, $onResolve);
            });

            $response->on("error", function($event) use($deferred) {
                $deferred->reject(new \Exception($event->getData()));
            });

            $streamSet = PromiseContext::getActiveStreamSet();
            if (is_null($streamSet)) {
                $deferred->reject(new \Exception("No promise context"));
                $stream->close();
                return;
            }
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
