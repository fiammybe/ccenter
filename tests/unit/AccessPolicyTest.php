<?php

use PHPUnit\Framework\TestCase;

final class AccessPolicyTest extends TestCase
{
    private function msg(array $over = []): array
    {
        return $over + ['uid' => 7, 'touid' => 5, 'onepass' => 'secret12', 'cgroup' => 9, 'status' => 'a'];
    }

    public function testGuestNeedsTheOneTimePassword(): void
    {
        $guest = new mod_ccenter_AccessPolicy(0, [], false);
        $this->assertTrue($guest->canViewMessage($this->msg(), 'secret12'));
        $this->assertFalse($guest->canViewMessage($this->msg(), 'wrong'));
        $this->assertFalse($guest->canViewMessage($this->msg(), ''));
    }

    public function testAnEmptyStoredPasswordNeverMatches(): void
    {
        $guest = new mod_ccenter_AccessPolicy(0, [], false);
        $this->assertFalse($guest->canViewMessage($this->msg(['onepass' => '']), ''));
        $this->assertFalse($guest->canViewMessage($this->msg(['onepass' => '']), null));
    }

    public function testSenderPersonInChargeGroupAndAdminMaySee(): void
    {
        $this->assertTrue((new mod_ccenter_AccessPolicy(7, [2], false))->canViewMessage($this->msg()));     // sender
        $this->assertTrue((new mod_ccenter_AccessPolicy(5, [2], false))->canViewMessage($this->msg()));     // in charge
        $this->assertTrue((new mod_ccenter_AccessPolicy(8, [2, 9], false))->canViewMessage($this->msg()));  // contact group
        $this->assertTrue((new mod_ccenter_AccessPolicy(1, [1], true))->canViewMessage($this->msg()));      // administrator
        $this->assertFalse((new mod_ccenter_AccessPolicy(8, [2], false))->canViewMessage($this->msg()));    // another member
    }

    public function testNoContactGroupMeansNobodyIsInIt(): void
    {
        $this->assertFalse((new mod_ccenter_AccessPolicy(8, [0, 2], false))->canViewMessage($this->msg(['cgroup' => 0])));
    }

    public function testAGuestIsNeverAnAdministratorOrGroupMember(): void
    {
        $guest = new mod_ccenter_AccessPolicy(0, [9], true);
        $this->assertFalse($guest->isAdmin());
        $this->assertFalse($guest->canViewMessage($this->msg()));
        $this->assertFalse($guest->canTakeOver($this->msg(['touid' => 0])));
    }

    public function testHandlingAForm(): void
    {
        $form = ['priuid' => 5, 'cgroup' => 9];
        $this->assertTrue((new mod_ccenter_AccessPolicy(5, [2], false))->handlesForm($form));
        $this->assertTrue((new mod_ccenter_AccessPolicy(8, [9], false))->handlesForm($form));
        $this->assertTrue((new mod_ccenter_AccessPolicy(1, [1], true))->handlesForm($form));
        $this->assertFalse((new mod_ccenter_AccessPolicy(8, [2], false))->handlesForm($form));
        $this->assertFalse((new mod_ccenter_AccessPolicy(0, [], false))->handlesForm(['priuid' => 0, 'cgroup' => 0]));
    }

    public function testTakingOverNeedsAnUnassignedMessageAndTheContactGroup(): void
    {
        $open = $this->msg(['touid' => 0]);
        $this->assertTrue((new mod_ccenter_AccessPolicy(8, [9], false))->canTakeOver($open));
        $this->assertTrue((new mod_ccenter_AccessPolicy(1, [1], true))->canTakeOver($open));
        $this->assertFalse((new mod_ccenter_AccessPolicy(8, [2], false))->canTakeOver($open));
        $this->assertFalse((new mod_ccenter_AccessPolicy(8, [9], false))->canTakeOver($this->msg()));   // someone is in charge
    }

    public function testOnlyThePersonInChargeChangesTheStatus(): void
    {
        $charge = new mod_ccenter_AccessPolicy(5, [2], false);
        $this->assertTrue($charge->canChangeStatus($this->msg(), 'b'));
        $this->assertFalse($charge->canChangeStatus($this->msg(), 'a'));   // unchanged
        $this->assertFalse((new mod_ccenter_AccessPolicy(8, [9], false))->canChangeStatus($this->msg(), 'b'));
        $this->assertFalse((new mod_ccenter_AccessPolicy(1, [1], true))->canChangeStatus($this->msg(), 'b')); // admin who is not in charge
    }

    public function testOrdinaryStaffCannotDeleteOrReset(): void
    {
        $staff = new mod_ccenter_AccessPolicy(5, [2], false);
        $this->assertSame(['a', 'b', 'c'], $staff->allowedStatuses());
        $this->assertFalse($staff->canChangeStatus($this->msg(), 'x'));
        $this->assertFalse($staff->canChangeStatus($this->msg(), '-'));
        $this->assertFalse($staff->canChangeStatus($this->msg(), 'z'));
        $admin = new mod_ccenter_AccessPolicy(5, [1], true);
        $this->assertSame(['-', 'a', 'b', 'c', 'x'], $admin->allowedStatuses());
        $this->assertTrue($admin->canChangeStatus($this->msg(), 'x'));
    }
}
