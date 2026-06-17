<?php
namespace KiriminAjaOfficial\Utils;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Logger {
    public const SOURCE = 'kiriminaja';

    private const LEVEL_SEVERITY = array(
        'emergency' => 0,
        'alert'     => 1,
        'critical'  => 2,
        'error'     => 3,
        'warning'   => 4,
        'notice'    => 5,
        'info'      => 6,
        'debug'     => 7,
    );

    public static function emergency( string $message, array $context = array() ): void {
        self::write( 'emergency', $message, $context );
    }

    public static function alert( string $message, array $context = array() ): void {
        self::write( 'alert', $message, $context );
    }

    public static function critical( string $message, array $context = array() ): void {
        self::write( 'critical', $message, $context );
    }

    public static function error( string $message, array $context = array() ): void {
        self::write( 'error', $message, $context );
    }

    public static function warning( string $message, array $context = array() ): void {
        self::write( 'warning', $message, $context );
    }

    public static function notice( string $message, array $context = array() ): void {
        self::write( 'notice', $message, $context );
    }

    public static function info( string $message, array $context = array() ): void {
        self::write( 'info', $message, $context );
    }

    public static function debug( string $message, array $context = array() ): void {
        self::write( 'debug', $message, $context );
    }

    public static function isValidLevel( string $level ): bool {
        return isset( self::LEVEL_SEVERITY[ $level ] );
    }

    public static function normalizeSource( string $source = '' ): string {
        $candidate = '' !== $source ? $source : self::SOURCE;
        $candidate = strtolower( (string) preg_replace( '/[^a-z0-9_]+/', '_', $candidate ) );
        $candidate = trim( $candidate, '_' );

        return '' !== $candidate ? $candidate : self::SOURCE;
    }

    private static function write( string $level, string $message, array $context = array() ): void {
        if ( ! function_exists( 'wc_get_logger' ) || ! self::isValidLevel( $level ) || ! self::shouldHandle( $level ) ) {
            return;
        }

        $message = self::normalizeMessage( $message );
        if ( '' === $message ) {
            return;
        }

        $context = self::normalizeContext( $level, $context );
        $logger  = wc_get_logger();

        if ( ! is_object( $logger ) || ! method_exists( $logger, $level ) ) {
            return;
        }

        $logger->{$level}( $message, $context );
    }

    private static function shouldHandle( string $level ): bool {
        $threshold = apply_filters(
            'kiriof_logger_threshold',
            ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? 'debug' : 'info',
            $level
        );

        if ( ! is_string( $threshold ) ) {
            return true;
        }

        $threshold = strtolower( trim( $threshold ) );
        if ( '' === $threshold || 'none' === $threshold || ! self::isValidLevel( $threshold ) ) {
            return true;
        }

        return self::LEVEL_SEVERITY[ $level ] <= self::LEVEL_SEVERITY[ $threshold ];
    }

    private static function normalizeMessage( string $message ): string {
        return trim( (string) preg_replace( '/\s+/', ' ', $message ) );
    }

    private static function normalizeContext( string $level, array $context ): array {
        $context['source']    = self::normalizeSource( (string) ( $context['source'] ?? self::SOURCE ) );
        $context['timestamp'] = current_time( 'mysql' );

        if ( self::isErrorLevel( $level ) && ! array_key_exists( 'backtrace', $context ) ) {
            $context['backtrace'] = true;
        }

        return $context;
    }

    private static function isErrorLevel( string $level ): bool {
        return in_array( $level, array( 'emergency', 'alert', 'critical', 'error', 'warning' ), true );
    }
}