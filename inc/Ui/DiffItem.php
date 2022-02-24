<?php

namespace dokuwiki\Ui;

use dokuwiki\ChangeLog\ChangeLog;

/**
 * Diff item representing first/second page/media
 * parent class of PageDiffItem and MediaDiffItem
 *
 * @package dokuwiki\Ui
 */
abstract class DiffItem
{
    public /*readonly*/ string $id;   // id
    protected int $perm;    // permission
    public /*readonly*/ ChangeLog $changeLog;  // Page/MediaChangeLog
    public /*readonly*/ $rev;     // int|false timestamp of revision
    public /*readonly*/ array $info;

    public const keyOfName = 'name';
    public const keyOfDate = 'date';
    public const keyOfTimestamp = 'timestamp';

    /**
     * Constructor
     *
     * @param string $id      page/media id
     * @param int|false $rev  page/media revision, 0 for current, negative for previous, false for no revision yet
     */
    protected function __construct(string $id, $rev = 0)
    {
        $this->id = $id;
        $this->perm = auth_quickaclcheck($this->id);
        if (!$this->canRead())
            return;
        $this->rev = $rev;
        if ($rev !== false)
        {
            $this->changeLog = $this->createChangeLog();
            if ($rev < 0)
            {
                $revs = $this->changeLog->getRevisions(0, 1);
                $this->rev = $revs[0] ?? 0;
            }
            if (!$rev)
                $this->rev = $this->changeLog->currentRevision();
            $info = $this->changeLog->getRevisionInfo($this->rev);
        }
        if (!$info) {
            $this->rev = false;
            $info = [];
        }
        $this->info = $info;
    }

    public function canRead() : bool
    {
        return $this->perm >= AUTH_READ;
    }

    public function validateCanRead() : bool
    {
        $canRead = $this->canRead();
        if (!$canRead)
            (new \dokuwiki\Action\Denied())->tplContent();
        return $canRead;
    }

    public function name() : string
    {
        $value = $this->info[self::keyOfName];
        if (!$value)
            $value = $this->id;
        return $value;
    }
    public function date()
    {
        return $this->info[self::keyOfDate];
    }
    public function hasTimestamp() : bool
    {
        return $this->info[self::keyOfTimestamp] ?? true;;
    }

    public function isCurrent() : bool
    {
        return
            $this->rev &&
            $this->changeLog->isCurrentRevision($this->date());
    }

    /**
     * Create changelog
     */
    abstract protected function createChangeLog() : ChangeLog;
}
