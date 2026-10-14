<?php
namespace KiriminAjaOfficial\Infrastructure;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Psr\Http\Message\StreamInterface;

/** PSR-7 response sink that rejects oversized writes before storing any bytes. */
final class BoundedResponseStream implements StreamInterface {
	public const MAX_BODY_BYTES = 2097152;

	private StreamInterface $stream;
	private int $max_bytes;

	/** Wrap a PSR-7 stream without extending Nyholm's final-by-contract stream. */
	public function __construct( StreamInterface $stream, int $max_bytes = self::MAX_BODY_BYTES ) {
		$size = $stream->getSize();
		if ( $max_bytes < 0 || null === $size || $size > $max_bytes ) {
			throw new \RuntimeException( 'Instant response exceeds limit.' );
		}
		$this->stream    = $stream;
		$this->max_bytes = $max_bytes;
	}

	public function __toString(): string {
		return (string) $this->stream;
	}

	public function close(): void {
		$this->stream->close();
	}

	public function detach() {
		return $this->stream->detach();
	}

	public function getSize(): ?int {
		return $this->stream->getSize();
	}

	public function tell(): int {
		return $this->stream->tell();
	}

	public function eof(): bool {
		return $this->stream->eof();
	}

	public function isSeekable(): bool {
		return $this->stream->isSeekable();
	}

	public function seek( int $offset, int $whence = SEEK_SET ): void {
		$this->stream->seek( $offset, $whence );
	}

	public function rewind(): void {
		$this->stream->rewind();
	}

	public function isWritable(): bool {
		return $this->stream->isWritable();
	}

	public function write( string $string ): int {
		$size   = $this->stream->getSize();
		$cursor = $this->stream->tell();
		$length = strlen( $string );
		// Check both existing size and cursor: a seek can create an oversized hole.
		if ( null === $size || $size > $this->max_bytes || $cursor > $this->max_bytes || $length > $this->max_bytes - $cursor ) {
			throw new \RuntimeException( 'Instant response exceeds limit.' );
		}
		return $this->stream->write( $string );
	}

	public function isReadable(): bool {
		return $this->stream->isReadable();
	}

	public function read( int $length ): string {
		return $this->stream->read( $length );
	}

	public function getContents(): string {
		return $this->stream->getContents();
	}

	public function getMetadata( ?string $key = null ) {
		return $this->stream->getMetadata( $key );
	}
}
