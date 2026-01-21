<?php

declare(strict_types=1);

use JayI\Cortex\Plugins\Tool\Contracts\ToolContract;
use JayI\Cortex\Support\ExtensionPoint;

interface TestExtensionInterface {}

class TestExtension implements TestExtensionInterface {}

class InvalidExtension {}

describe('ExtensionPoint', function () {
    it('creates extension point with make', function () {
        $point = ExtensionPoint::make('test_point', TestExtensionInterface::class);

        expect($point->name())->toBe('test_point');
        expect($point->accepts())->toBe(TestExtensionInterface::class);
    });

    it('registers valid extensions', function () {
        $point = ExtensionPoint::make('test_point', TestExtensionInterface::class);

        $extension = new TestExtension;
        $point->register($extension);

        expect($point->all()->count())->toBe(1);
        expect($point->all()->first())->toBe($extension);
    });

    it('registers multiple extensions', function () {
        $point = ExtensionPoint::make('test_point', TestExtensionInterface::class);

        $ext1 = new TestExtension;
        $ext2 = new TestExtension;
        $ext3 = new TestExtension;

        $point->register($ext1);
        $point->register($ext2);
        $point->register($ext3);

        expect($point->all()->count())->toBe(3);
    });

    it('throws exception for invalid extension type', function () {
        $point = ExtensionPoint::make('test_point', TestExtensionInterface::class);

        $invalidExtension = new InvalidExtension;

        expect(fn () => $point->register($invalidExtension))
            ->toThrow(InvalidArgumentException::class);
    });

    it('returns correct name', function () {
        $point = ExtensionPoint::make('my_extension', TestExtensionInterface::class);

        expect($point->name())->toBe('my_extension');
    });

    it('returns correct accepts type', function () {
        $point = ExtensionPoint::make('tools', ToolContract::class);

        expect($point->accepts())->toBe(ToolContract::class);
    });

    it('starts with empty collection', function () {
        $point = ExtensionPoint::make('empty_point', TestExtensionInterface::class);

        expect($point->all()->isEmpty())->toBeTrue();
    });
});
