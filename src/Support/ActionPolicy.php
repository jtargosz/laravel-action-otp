<?php

namespace Jtargosz\ActionOtp\Support;

use Illuminate\Notifications\Notification;
use InvalidArgumentException;
use Jtargosz\ActionOtp\Attributes\AnyDevice;
use Jtargosz\ActionOtp\Attributes\CodeFormat;
use Jtargosz\ActionOtp\Attributes\CodeLength;
use Jtargosz\ActionOtp\Attributes\SendWith;
use Jtargosz\ActionOtp\Attributes\Ttl;
use Jtargosz\ActionOtp\Contracts\VerifiableAction;
use ReflectionClass;

/**
 * Code settings for one action: attributes on the action class (or its
 * parents) win, config fills the rest.
 */
final class ActionPolicy
{
    /**
     * Attribute reads per class. These are reset between tests by
     * resetAttributes(). Config fallbacks are resolved on every call,
     * so config changes still apply.
     *
     * @var array<class-string, array{ttl: int|null, format: string|null, length: int|null, notification: string|null, any_device: bool}>
     */
    private static array $attributes = [];

    public static function resetAttributes(): void
    {
        self::$attributes = [];
    }

    /**
     * @param  class-string<Notification>  $notification
     */
    public function __construct(
        public readonly int $ttlMinutes,
        public readonly string $format,
        public readonly int $length,
        public readonly string $notification,
        public readonly bool $anyDevice,
    ) {}

    public static function from(VerifiableAction $action): self
    {
        $read = self::$attributes[$action::class] ??= self::read($action::class);

        $notification = $read['notification'] ?? (string) config('action-otp.notification');

        if (! is_a($notification, Notification::class, true)) {
            throw new InvalidArgumentException('OTP notification must extend Illuminate\Notifications\Notification.');
        }

        return new self(
            ttlMinutes: max(1, $read['ttl'] ?? (int) config('action-otp.ttl_minutes', 15)),
            format: $read['format'] ?? (string) config('action-otp.code_format', 'numeric'),
            length: $read['length'] ?? (int) config('action-otp.code_length', 6),
            notification: $notification,
            anyDevice: $read['any_device'],
        );
    }

    /**
     * @param  class-string  $class
     * @return array{ttl: int|null, format: string|null, length: int|null, notification: string|null, any_device: bool}
     */
    private static function read(string $class): array
    {
        return [
            'ttl' => self::attribute($class, Ttl::class)?->minutes,
            'format' => self::attribute($class, CodeFormat::class)?->format,
            'length' => self::attribute($class, CodeLength::class)?->length,
            'notification' => self::attribute($class, SendWith::class)?->notification,
            'any_device' => self::attribute($class, AnyDevice::class) !== null,
        ];
    }

    /**
     * Closest declaration wins: the class itself, then its parents.
     *
     * @template T of object
     *
     * @param  class-string  $class
     * @param  class-string<T>  $attribute
     * @return T|null
     */
    private static function attribute(string $class, string $attribute): ?object
    {
        $reflection = new ReflectionClass($class);

        do {
            $found = $reflection->getAttributes($attribute);

            if ($found !== []) {
                return $found[0]->newInstance();
            }
        } while ($reflection = $reflection->getParentClass());

        return null;
    }
}
