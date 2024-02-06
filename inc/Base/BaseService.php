<?php

namespace Inc\Base;

use Inc\Utils\ServiceResponse;

class BaseService{
    /**
     * To return success response of the service
     *
     * @param $data
     * @param string $message
     * @param string|null $customCode
     * @return ServiceResponse
     */
    protected static function success($data, string $message = "success", string $customCode = null): ServiceResponse {
        return new ServiceResponse($data, $message, 200, $customCode);
    }

    /**
     * To return error response of the service
     *
     * @param $data
     * @param string $message
     * @param int $status
     * @param string|null $customCode
     * @return ServiceResponse
     */
    protected static function error($data, string $message = "error", int $status = 400, string $customCode = null): ServiceResponse {
        return new ServiceResponse($data, $message, $status, $customCode);
    }
    
    
}