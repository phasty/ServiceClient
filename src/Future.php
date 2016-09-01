<?php
namespace Phasty\ServiceClient {
    use \Phasty\Stream\Stream;
    use \Phasty\Stream\StreamSet;
    use \Phasty\Stream\Timer;

    class Future {
        protected $value     = null;
        protected $socket    = null;
        protected $resolved  = false;
        protected $rejected  = false;
        protected $data      = "";
        protected $streamSet = null;
        protected $onResolve;

        /**
         * Метод-фабрика для конструктора
         *
         * @param Stream   $stream       поток для чтения ответа
         * @param callable $onResolve   callback функция для обработки результата операции
         *
         * @return Future
         */
        static public function create($stream, $onResolve) {
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
         * Устанавливает зарезолвленное значение. Если значение является исключением
         *
         * @param mixed $value Ответ асинхронной операции либо исключение
         */
        public function resolveWith($value) {
            if (is_callable($this->onResolve) && !($value instanceof \Exception)) {
                try {
                    $value = call_user_func($this->onResolve, $value);
                } catch (\Exception $e) {
                    $value = $e;
                }
            }

            if ($value instanceof \Exception) {
                $this->rejected = true;
            }

            $this->value = $value;
            $this->resolved = true;
        }

        /**
         * Устанавливает слушателей на сокет
         */
        protected function setListeners() {
            $this->socket->on("data", function($data)  {
                $this->data .= $data->getData();
            });

            $this->socket->on("close", function() {
                $this->streamSet->stop();
            });
        }

        /**
         * Если результат выполнения операции уже известен, возвращает его, иначе блокируется до получения ответа
         *
         * @return mixed Результат выполнения Future
         */
        public function resolve() {
            if (!$this->resolved) {
                $value = null;
                try {

                    $response = new \Phasty\Server\Http\Response();
                    $response->setReadStream($this->socket);

                    $response->on("read-complete", function ($event, $response) use (&$value) {
                        set_error_handler(function() {});
                        $body = json_decode($response->getBody(), true);
                        restore_error_handler();

                        if (is_null($body)) {
                            throw new \Exception("Service response is not json:\n " . $response->getBody());
                        } elseif ($response->getCode() > 299) {
                           throw new \Exception($body[ "message" ], $response->getCode());
                        } else {
                            $value = $body[ "result" ];
                        }
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
                    $value = $e;
                }
                $this->resolveWith($value);
            }
            return $this->value;
        }

        /**
         * Произошла ли ошибка при резолвинге
         *
         * @return bool Была ошибка в процессе выполнения или нет
         */
        public function isRejected() {
            return $this->rejected;
        }
    }
}
