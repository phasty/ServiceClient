<?php
namespace Phasty\ServiceClient {

    use \React\Promise\Deferred;
    use \Phasty\Stream\StreamSet;
    use \Phasty\Stream\Stream;
    use \Phasty\Stream\Timer;

    /**
     * Class Promise
     * Класс для создания промиса
     *
     * @package Phasty\ServiceClient
     */
    class Promise {

        /**
         * Метод-фабрика для конструктора Promise
         *
         * @param mixed         $stream
         * @param null|callable $onResolve  Функция-обработчик результата
         * @param null|callable $onReject   Функция-обработчик неуспешного результата
         *
         * @return \React\Promise\Promise|\React\Promise\PromiseInterface
         */
        public static function create($stream, $onResolve, $onReject) {
            $deferred = new Deferred();
            if (!$stream instanceof Stream) {
                self::resolveWith($deferred, $stream, $onResolve, $onReject);
            } else {
                self::setListenersToStream($stream, $deferred, $onResolve, $onReject);
            }
            return $deferred->promise();
        }

        /**
         * Устанавливает зарезолвленное значение. При этом вызываются установленные обработчки на успешного результата
         * или ошибки. Если значение является исключением результат промиса принимает состояние rejected.
         *
         * @param Deferred      $deferred
         * @param mixed         $result     Ответ асинхронной операции либо исключение
         * @param null|callable $onResolve  Функция-обработчик результата
         * @param null|callable $onReject   Функция-обработчик неуспешного результата
         */
        public static function resolveWith($deferred, $result, $onResolve, $onReject) {
            $callback = ($result instanceof \Exception) ? $onReject : $onResolve;
            if (is_callable($callback)) {
                try {
                    $result = call_user_func($callback, $result);
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
        protected static function setListenersToStream(Stream $stream, $deferred, $onResolve, $onReject) {
            $response = new \Phasty\Server\Http\Response();
            $response->setReadStream($stream);

            $response->on("read-complete", function($event, $response) use ($deferred, $onResolve, $onReject) {
                $result = Result::processResponse($response);
                Promise::resolveWith($deferred, $result, $onResolve, $onReject);
            });

            $response->on("error", function($event) use ($deferred, $onResolve, $onReject) {
                Promise::resolveWith($deferred, new \Exception($event->getData()), $onResolve, $onReject);
            });

            $streamSet = PromiseContext::getActiveStreamSet();
            if (is_null($streamSet)) {
                Promise::resolveWith($deferred, new \Exception("No promise context"), $onResolve, $onReject);
                $stream->close();
                return;
            }
            $streamSet->addReadStream($stream);

            $timer = new Timer(
                Result::OPERATION_TIMEOUT_SECONDS,
                Result::OPERATION_TIMEOUT_MICROSECONDS,
                function() use ($stream, $deferred, $onResolve, $onReject) {
                    Promise::resolveWith($deferred, new \Exception("Operation timed out"), $onResolve, $onReject);
                    $stream->close();
                }
            );

            $streamSet->addTimer($timer);
        }
    }
}
