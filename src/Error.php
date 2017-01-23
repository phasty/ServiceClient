<?php
namespace Phasty\ServiceClient {

    /**
     * Class Error
     * HTTP Status: 500
     * Исключение выбрасываемое в случае возникновения технической ошибки на стороне сервера.
     * Общий класс, для того чтобы в коде было легко отделить ошибки сервиса от прочих исключений.
     *
     * @package Phasty\ServiceClient
     */
    abstract class Error extends \Exception {

        const INTERNAL_ERROR      = 1;
        const API_NOT_IMPLEMENTED = 2;
        const BAD_REQUEST         = 3;

    }
}