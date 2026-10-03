<?php

namespace Tests\Unit;

use App\Models\Addon;
use PHPUnit\Framework\TestCase;

class AddonLifecycleStateTest extends TestCase
{
    public function test_core_addon_lifecycle_contains_all_required_states(): void
    {
        $this->assertSame([
            'draft',
            'validating',
            'installing',
            'installed',
            'enabling',
            'active',
            'disabling',
            'inactive',
            'updating',
            'uninstalling',
            'failed',
            'archived',
        ], Addon::LIFECYCLE_STATES);
    }

    public function test_transitional_states_have_safe_terminal_paths(): void
    {
        $addon = new Addon(['status' => 'installed']);
        $this->assertTrue($addon->canTransitionTo('enabling'));
        $this->assertTrue($addon->canTransitionTo('uninstalling'));

        $addon->status = 'enabling';
        $this->assertTrue($addon->canTransitionTo('active'));
        $this->assertTrue($addon->canTransitionTo('failed'));

        $addon->status = 'active';
        $this->assertTrue($addon->canTransitionTo('disabling'));

        $addon->status = 'disabling';
        $this->assertTrue($addon->canTransitionTo('inactive'));
        $this->assertTrue($addon->canTransitionTo('failed'));

        $addon->status = 'uninstalling';
        $this->assertTrue($addon->canTransitionTo('archived'));
        $this->assertTrue($addon->canTransitionTo('failed'));
    }
}
