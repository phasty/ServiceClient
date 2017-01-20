<?php
namespace Phasty\ServiceClient {

    /**
     * Class InternalError
     * HTTP Status: 500
     * Исключение выбрасываемое в случае возникновения технической ошибки на стороне сервера.
     *
     * @package Phasty\ServiceClient
     */
    abstract class Error extends \Exception {

        const INTERNAL_ERROR      = 1;
        const API_NOT_IMPLEMENTED = 2;
        const BAD_REQUEST         = 3;

    }
}