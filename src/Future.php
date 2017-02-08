<?php
namespace Phasty\ServiceClient {

    use \Phasty\Stream\Stream;
    use \Phasty\Stream\StreamSet;
    use \Phasty\Stream\Timer;

    class Future {
        protected $value     = null;
        protected $stream    = null;
        protected $resolved  = false;
        protected $onResolve = null;
        protected $onReject  = null;

        /**
         * Метод-фабрика для конструктора
         *
         * @param Stream   $stream       поток для чтения ответа
         * @param callable $onResolve   callback функция для обработки результата операции
         * @param callable $onReject    функция-обработчик неуспешного результата (исключения)
         *
         * @return Future
         */
        public static function create($stream, $onResolve, $onReject) {
            return new static($stream, $onResolve, $onReject);
        }

        /**
         * Конструктор. Принимает зарезолвленный результат, либо стрим, из которого его нужно получить.
         * Конструктор закрыт - работаем с фабриками!
         *
         * @param Stream        $stream
         * @param null|callable $onResolve  функция-обработчик результата
         * @param null|callable $onReject   функция-обработчик неуспешного результата (исключения)
         */
        protected function __construct($stream, $onResolve, $onReject) {
            $this->onResolve = $onResolve;
            $this->onReject = $onReject;
            if (!$stream instanceof Stream) {
                $this->resolveWith($stream);
                return;
            }
            $this->stream = $stream;
        }

        /**
         * Устанавливает зарезолвленное значение, которое может являться исключением
         *
         * @param mixed $value Ответ асинхронной операции либо исключение
         */
        protected function resolveWith($value) {
            if (is_callable($this->onResolve) && !($value instanceof \Exception)) {
                try {
                    $value = call_user_func($this->onResolve, $value);
                } catch (\Exception $e) {
                    $value = $e;
                }
            }

            // Если в результате попытки резолва произошла беда, или беда вернулась из сервиса
            if (($value instanceof \Exception) && is_callable($this->onReject)) {
                try {
                    $value = call_user_func($this->onReject, $value);
                } catch (\Exception $e) {
                    // Тут уже вообще все плохо - все сломалось в onReject!
                    $value = $e;
                }
            }

            $this->value = $value;
            $this->resolved = true;
        }

        /**
         * Если результат выполнения операции уже известен, возвращает его, иначе блокируется до получения ответа
         *
         * @return mixed Результат выполнения Future
         */
        public function resolve() {
            if (!$this->resolved) {
                $result = null;
                try {
                    $response = new \Phasty\Server\Http\Response();
                    $response->setReadStream($this->stream);

                    $response->on("read-complete", function ($event, $response) use (&$result) {
                        $result = Result::processResponse($response);
                    });

                    $response->on("error", function ($event) {
                        throw new \Exception($event->getBody());
                    });

                    $timer = new Timer(
                        Result::OPERATION_TIMEOUT_SECONDS,
                        Result::OPERATION_TIMEOUT_MICROSECONDS,
                        function() {
                            $this->stream->close();
                            throw new \Exception("Operation timed out");
                        }
                    );

                    $streamSet = new StreamSet();
                    $streamSet->addReadStream($this->stream);
                    $streamSet->addTimer($timer);

                    $streamSet->listen();
                } catch (\Exception $e) {
                    $result = $e;
                }
                $this->resolveWith($result);
            }
            return $this->value;
        }
    }
}
