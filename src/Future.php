<?php
namespace Phasty\ServiceClient {

    use \Phasty\Stream\Stream;
    use \Phasty\Stream\StreamSet;
    use \Phasty\Stream\Timer;

    /**
     * Class Future
     * Класс для работы с Future
     *
     * @package Phasty\ServiceClient
     */
    class Future {
        protected $value     = null;
        protected $stream    = null;
        protected $resolved  = false;
        protected $onResolve = null;
        protected $onReject  = null;

        /**
         * Метод-фабрика для конструктора Future
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
         * Устанавливает зарезолвленное значение, которое может являться исключением.
         * При этом вызываются обработчики результата в случае успеха или неудачи.
         *
         * @param mixed $value Ответ асинхронной операции либо исключение
         */
        protected function resolveWith($value) {
            $callback = ($value instanceof \Exception) ? $this->onReject : $this->onResolve;
            if (is_callable($callback)) {
                try {
                    $value = call_user_func($callback, $value);
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
