<?php
namespace KiriminAjaOfficial\Services\TransactionProcessServices;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use KiriminAjaOfficial\Base\BaseService;

class ValidatePinService extends BaseService {
    private string $pin = '';

    public function pin( string $pin ) {
        $this->pin = $pin;
        return $this;
    }

    public function call() {
        if ( strlen( $this->pin ) !== 6 || ! ctype_digit( $this->pin ) ) {
            return self::error(
                [ 'error' => 'INVALID_REQUEST_FORMAT' ],
                __( 'PIN must be 6 digits', 'kiriminaja-official' )
            );
        }

        try {
            $result = ( new \KiriminAjaOfficial\Repositories\KiriminajaApiRepository() )->pinValidate( $this->pin );

            if ( true !== ( $result['status'] ?? null ) || ! is_object( $result['data'] ?? null ) ) {
                return self::error(
                    [ 'error' => 'PIN_VALIDATE_FAILED' ],
                    'PIN validation failed'
                );
            }

            $data = $result['data'];

            if ( isset( $data->data->error ) && $data->data->error === 'PIN_MAX_ATTEMPT_REACHED' ) {
                return self::error(
                    [
                        'error'      => 'PIN_MAX_ATTEMPT_REACHED',
                        'lock_until' => $data->data->lock_until ?? null,
                    ],
                    'PIN max attempts reached'
                );
            }

            // Current API uses envelope status; older payloads explicitly include valid.
            $valid    = true === ( $data->status ?? null ) && true === ( $data->data->valid ?? true );
            $attempt  = (int) ( $data->data->attempt ?? 0 );
            $max      = (int) ( $data->data->max_attempt ?? 3 );

            if ( ! $valid ) {
                return self::error(
                    [
                        'valid'       => false,
                        'attempt'     => $attempt,
                        'max_attempt' => $max,
                    ],
                    'Invalid PIN'
                );
            }

            return self::success(
                [
                    'valid'       => true,
                    'attempt'     => $attempt,
                    'max_attempt' => $max,
                ],
                'PIN valid'
            );
        } catch ( \Throwable $th ) {
            return self::error(
                [ 'error' => 'PIN_VALIDATE_FAILED' ],
                'PIN validation failed'
            );
        }
    }
}
