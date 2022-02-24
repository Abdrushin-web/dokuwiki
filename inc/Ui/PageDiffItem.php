<?php

namespace dokuwiki\Ui;

use dokuwiki\ChangeLog\ChangeLog;
use dokuwiki\ChangeLog\PageChangeLog;

/**
 * Diff item representing first/second page
 *
 * @package dokuwiki\Ui
 */
class PageDiffItem extends DiffItem
{
    public const keyOfText = 'text';

    /**
     * Constructor
     *
     * @param string $id  page id
     * @param int|false $rev  page revision, 0 for current, negative for previous, false for no revision yet
     */
    public function __construct(string $id, $rev = 0)
    {
        parent::__construct($id, $rev);
    }

    /** @inheritdoc */
    protected function createChangeLog() : ChangeLog
    {
        return new PageChangeLog($this->id);
    }
}
