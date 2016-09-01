<?php
namespace Phasty\ServiceClient {
    use \Phasty\Stream\Stream;

    class Result {
        const OPERATION_TIMEOUT_MICROSECONDS = 3000000;
        const OPERATION_TIMEOUT_SECONDS      = 20;
        protected $stream  = null;
        protected $promise = null;
        protected $future  = null;
        protected $onResolve;

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
         * Порождает объект класса React\Promise.
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
            if ($future->isRejected()) {
                throw new \Exception($result);
            }
            return $result;
        }
    }
}
