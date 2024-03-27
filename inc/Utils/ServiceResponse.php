<?php

namespace Inc\Utils;

class ServiceResponse{

    /**
     * Setter of response service
     *
     * @param $data
     * @param string $message
     * @param int $status
     * @param string|null $customCode
     */
    public function __construct(public $data, public string $message, public int $status = 200, public ?string $customCode = null) {
        $this->status       = $status;
        $this->message      = $message;
        $this->data         = $data;
        $this->customCode   = $customCode;
    }

    public function status(): int {
        return $this->status;
    }

    public function message(): string {
        return $this->message;
    }

    public function data() {
        return $this->data;
    }

    public function customCode(): string
    {
        return $this->customCode;
    }
    
}