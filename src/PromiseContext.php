<?php
namespace Phasty\ServiceClient {

    use Phasty\Stream\StreamSet;

    class PromiseContext {

        /**
         * @var null|PromiseContext
         */
        protected static $activeContext = null;

        /**
         * @var null|PromiseContext
         */
        protected $parent = null;
        /**
         * @var null|StreamSet
         */
        protected $streamSet = null;

        /**
         * @return null|PromiseContext
         */
        protected static function getActive() {
            return static::$activeContext;
        }

        /**
         * @param null|PromiseContext $context
         */
        protected static function setActive($context) {
            static::$activeContext = $context;
        }

        /**
         * @return null|StreamSet
         */
        public static function getActiveStreamSet() {
            if (!is_null($activeContext = self::getActive())) {
                return $activeContext->streamSet;
            } else {
                return null;
            }
        }

        /**
         * PromiseContext constructor.
         *
         * @param PromiseContext $parent
         */
        protected function __construct($parent = null) {
            $this->parent    = $parent;
            $this->streamSet = new StreamSet();
        }

        /**
         * @return null|PromiseContext
         */
        protected function getParent() {
            return $this->parent;
        }

        protected static function push() {
            $context = new static(self::getActive());
            static::setActive($context);
        }

        /**
         * @throws \Exception
         */
        protected static function pop() {
            $activeContext = static::getActive();
            if (is_null($activeContext)) {
                throw new \Exception("No active context");
            }
            $context = $activeContext->getParent();
            static::setActive($context);
        }

        protected static function listen() {
            $activeStreamSet = static::getActiveStreamSet();
            if (!is_null($activeStreamSet) && $activeStreamSet->getReadStreamsCount() > 0) {
                $activeStreamSet->listen();
            }
        }

        /**
         * @param mixed $callable
         * @param array $arguments
         *
         * @return mixed
         * @throws \Exception
         */
        public static function wait($callable, $arguments = []) {
            $result = $error = $resolved = false;
            static::push();
            try {
                $functionResult = call_user_func_array($callable, $arguments);
                if ($functionResult instanceof \React\Promise\PromiseInterface) {
                    $functionResult->then(
                        function ($promiseResult) use (&$result) {
                            $result = $promiseResult;
                        },
                        function ($e) use (&$error) {
                            $error = $e;
                        }
                    )->then(
                        function () use (&$resolved) {
                            $resolved = true;
                        }
                    );
                } else {
                    $result   = $functionResult;
                    $resolved = true;
                }
                static::listen();
                if (!$resolved) {
                    $error = new \Exception("Out of context call");
                }
            } catch (\Exception $e) {
                $error = $e;
            }
            static::pop();
            if ($error instanceof \Exception) {
                throw $error;
            }
            return $result;
        }
    }
}
