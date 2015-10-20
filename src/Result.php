<?php
namespace Phasty\ServiceClient {
    use \Phasty\Stream\Stream;

    class Result {
        protected $stream  = null;
        protected $promise = null;
        protected $future  = null;

        public function __construct(Stream $stream) {
            $this->stream = $stream;
        }

        public function promise() {
            if ($this->promise) {
                return $this->promise;
            }
            return $this->promise = Promise::create($this->stream);
        }

        public function future() {
            if ($this->future) {
                return $this->future;
            }
            return $this->future = Future::create($this->stream);
        }
    }
}
