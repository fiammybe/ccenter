<?php

/**
 * Who may see and handle what. The rules work on plain values (user id, groups, whether the user is a
 * module administrator) so they can be tested without a CMS; forCurrentUser() fills them in.
 *
 *  - A message can be seen by: the visitor who knows its one-time password, module administrators,
 *    members of the contact group of its form, the sender and the person in charge.
 *  - Messages of a form are handled by: module administrators, the person in charge of the form
 *    and members of its contact group.
 *  - The status of a message is changed by the person in charge, to a status that is allowed for the role;
 *    only administrators may mark a message as deleted or reset it to "new".
 *  - A message nobody is in charge of can be taken over by administrators and members of the contact group.
 */
class mod_ccenter_AccessPolicy
{
    /** @var int */
    private $uid;
    /** @var int[] */
    private $groups;
    /** @var bool */
    private $admin;

    /**
     * @param int   $uid     0 for a guest
     * @param int[] $groups  ids of the user's groups
     * @param bool  $isAdmin whether the user administers this module
     */
    public function __construct($uid, array $groups, $isAdmin)
    {
        $this->uid = (int) $uid;
        $this->groups = array_map('intval', $groups);
        $this->admin = (bool) $isAdmin && $this->uid > 0;
    }

    /** The policy for the visitor of the current request. */
    public static function forCurrentUser()
    {
        if (!is_object(icms::$user)) {
            return new self(0, [], false);
        }
        $mid = is_object(icms::$module) ? icms::$module->getVar('mid') : 0;
        return new self(icms::$user->getVar('uid'), icms::$user->getGroups(), icms::$user->isAdmin($mid));
    }

    public function uid()
    {
        return $this->uid;
    }

    public function isAdmin()
    {
        return $this->admin;
    }

    /** @return bool whether the user is in the given group (0 = no group) */
    public function inGroup($groupId)
    {
        return $this->uid > 0 && (int) $groupId > 0 && in_array((int) $groupId, $this->groups, true);
    }

    /**
     * @param array  $message  row with uid, touid, onepass and the cgroup of its form
     * @param string $password one-time password the visitor knows (from the link or the session)
     */
    public function canViewMessage(array $message, $password = '')
    {
        $stored = (string) ($message['onepass'] ?? '');
        if ($stored !== '' && is_string($password) && hash_equals($stored, $password)) {
            return true;
        }
        if ($this->admin || $this->inGroup($message['cgroup'] ?? 0)) {
            return true;
        }
        return $this->uid > 0 && ((int) ($message['uid'] ?? 0) === $this->uid || (int) ($message['touid'] ?? 0) === $this->uid);
    }

    /** @param array $form row with priuid and cgroup */
    public function handlesForm(array $form)
    {
        return $this->admin
            || ($this->uid > 0 && (int) ($form['priuid'] ?? 0) === $this->uid)
            || $this->inGroup($form['cgroup'] ?? 0);
    }

    /** @param array $message row with touid and the cgroup of its form */
    public function canTakeOver(array $message)
    {
        return $this->uid > 0
            && (int) ($message['touid'] ?? 0) === 0
            && ($this->admin || $this->inGroup($message['cgroup'] ?? 0));
    }

    /**
     * Status values this user may set on a message he or she is in charge of.
     *
     * @return string[]
     */
    public function allowedStatuses()
    {
        $all = mod_ccenter_Status::all();
        return $this->admin ? $all : array_slice($all, 1, 3); // a, b, c
    }

    /**
     * Administrators change the status of any message (message administration).
     *
     * @param array  $message row with status
     * @param string $status  the wanted status
     */
    public function canAdminChangeStatus(array $message, $status)
    {
        return $this->admin && mod_ccenter_Status::isValid($status) && ($message['status'] ?? null) !== $status;
    }

    /**
     * @param array  $message row with touid and status
     * @param string $status  the wanted status
     */
    public function canChangeStatus(array $message, $status)
    {
        return $this->uid > 0
            && (int) ($message['touid'] ?? 0) === $this->uid
            && in_array($status, $this->allowedStatuses(), true)
            && ($message['status'] ?? null) !== $status;
    }
}
