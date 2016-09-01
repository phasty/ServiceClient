<?php
namespace Phasty\ServiceClient {
    use \Phasty\Stream\Stream;

    class Result {
        const OPERATION_TIMEOUT_MICROSECONDS = 3000000;
        const OPERATION_TIMEOUT_SECONDS      = 20;
        protected $stream    = null;
        protected $promise   = null;
        protected $future    = null;
        protected $onResolve = null;

        public function __construct($stream) {
            $this->stream = $stream;
        }

        /**
         * Задает функцию для обработки результата запроса. Полезно для кеширования результата.
         * Функция должна возвращать обработанные данные
         *
         * @param callable $func функция принимает на входе значение - результат запроса к сервису.
         *
         * @return Result
         *
         * @throws \Exception
         */
        public function onResolve(callable $func) {
            if ($this->future || $this->promise) {
                throw new \Exception("You could not assign callback after process has been executed!");
            }
            $this->onResolve = $func;
            return $this;
        }

        /**
         * Обрабатыват результат запроса с сервиса.
         *
         * @param \Phasty\Server\Http\Response $response
         *
         * @return mixed $result Результат выполнения
         */
        public static function processResponse($response) {
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
            return $result;
        }
        /**
         * Порождает объект класса React\Promise.
         *
         * @return mixed  Возвращает React\Promise
         *
         * @throws \Exception если результатом запроса уже является Future
         */
        public function promise() {
            if ($this->future) {
                throw new \Exception("You could not give promise when future is in!");
            }
            if ($this->promise) {
                return $this->promise;
            }
            return $this->promise = Promise::create($this->stream, $this->onResolve);
        }
        /**
         * Порождает объект класса Future.
         *
         * @return Future  Возвращает Future
         *
         * @throws \Exception если результатом запроса уже является Promise
         */
        public function future() {
            if ($this->promise) {
                throw new \Exception("You could not emit future when promise was given!");
            }
            if ($this->future) {
                return $this->future;
            }
            return $this->future = Future::create($this->stream, $this->onResolve);
        }

        /**
         * Выполняет синхронный запрос с ипользованием объекта Future
         *
         * @return mixed Результат операции
         *
         * @throws \Exception  если результатом запроса уже является Promise
         */
        public function sync() {
            $future = $this->future();
            $result = $future->resolve();
            if ($result instanceof \Exception) {
                throw $result;
            }
            return $result;
        }
    }
}
