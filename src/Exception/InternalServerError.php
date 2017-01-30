<?php
namespace Phasty\ServiceClient\Exception {

    use Phasty\ServiceClient\Error;

    /**
     * Class InternalServerError
     * HTTP Status: 500
     * Исключение выбрасываемое в случае возникновения технической ошибки на стороне сервера.
     *
     * Исключение должно быть сгенерировано с кодом 1!
     *
     * @package Phasty\ServiceClient\Exception
     */
    class InternalServerError extends Error {

    }
}