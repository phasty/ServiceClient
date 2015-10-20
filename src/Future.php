<?php
namespace Ru7733\ServiceClient {
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

        /**
         * Метод-фабрика для конструктора
         */
        static public function create($stream) {
            return new static($stream);
        }

        /**
         * Конструктор. Принимает зарезолвленный результат, либо стрим, из которого его нужно получить
         */
        public function __construct($stream) {
            if (!$stream instanceof Stream) {
                $this->resolveWith($stream);
                return;
            }
            $this->socket = $stream;
        }

        /**
         * Устанавливает зарезолвленное значение
         *
         * @param mixed $value Ответ асинхронной операции либо исключение
         */
        protected function resolveWith($value) {
            $this->value = $value;
            $this->resolved = true;
            if ($value instanceof \Exception) {
                $this->rejected = true;
                $this->value = $value->getMessage();
            } else {
                $this->value = $value;
            }
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
         */
        public function resolve() {
            if ($this->resolved) {
                return $this->value;
            }
            $value = null;
            try {

                $response = new \Phasty\Server\Http\Response();
                $response->setReadStream($this->socket);

                $response->on("read-complete", function($event, $response) use(&$value) {
                    $body = json_decode($response->getBody(), true);
                    if ($response->getCode() > 299) {
                        throw new \Exception($body[ "message" ]);
                    }
                    $value = $body[ "result" ];
                });

                $response->on("error", function($event) {
                    throw new \Exception($event->getBody());
                });

                $timer = new Timer(1, 3000000, function() {
                    $this->socket->close();
                    throw new \Exception("Operation timed out");
                });

                $streamSet = new \Phasty\Stream\StreamSet();
                $streamSet->addReadStream($this->socket);
                $streamSet->addTimer($timer);

                $streamSet->listen();
            } catch (\Exception $e) {
                $value = $e;
            }
            $this->resolveWith($value);
            return $this->value;
        }

        /**
         * Произошла ли ошибка при резолвинге
         */
        public function isRejected() {
            return $this->rejected;
        }
    }
}
