<?php
namespace Phasty\ServiceClient {
    use \Phasty\Stream\Stream;
    use \Phasty\Stream\StreamSet;
    use \Phasty\Stream\Timer;

    class Future {
        protected $value     = null;
        protected $socket    = null;
        protected $resolved  = false;
        protected $onResolve = null;

        /**
         * Метод-фабрика для конструктора
         *
         * @param Stream   $stream       поток для чтения ответа
         * @param callable $onResolve   callback функция для обработки результата операции
         *
         * @return Future
         */
        public static function create($stream, $onResolve) {
            return new static($stream, $onResolve);
        }

        /**
         * Конструктор. Принимает зарезолвленный результат, либо стрим, из которого его нужно получить.
         * Конструктор закрыт - работаем с фабриками!
         *
         * @param Stream        $stream
         * @param null|callable $onResolve  функция-обработчик результата
         */
        protected function __construct($stream, $onResolve) {
            $this->onResolve = $onResolve;
            if (!$stream instanceof Stream) {
                $this->resolveWith($stream);
                return;
            }
            $this->socket = $stream;
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
                    $response->setReadStream($this->socket);

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
                            $this->socket->close();
                            throw new \Exception("Operation timed out");
                        }
                    );

                    $streamSet = new StreamSet();
                    $streamSet->addReadStream($this->socket);
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
