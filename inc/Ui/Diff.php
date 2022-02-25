<?php

namespace dokuwiki\Ui;

use dokuwiki\Ui\DiffItem;

/**
 * DokuWiki Diff Interface
 * parent class of PageDiff and MediaDiff
 *
 * @package dokuwiki\Ui
 */
abstract class Diff extends Ui
{
    protected DiffItem $item1;   // first page or media
    protected DiffItem $item2;   // second page or media

    /* @var array */
    protected $preference = [];

    /**
     * Diff Ui constructor
     *
     * @param string $id  page id or media id
     */
    public function __construct($id)
    {
        $this->id = $id;
    }

    /**
     * Create diff item
     * @param string $id       item identifier
     * @param int|bool $rev    item revision, 0 for current, negative for previous, false for no revision yet
     */
    abstract protected function createItem(string $id, $rev = 0) : DiffItem;

    /**
     * Prepare revision info of comparison pair
     */
    abstract protected function preProcess();

    /**
     * Gets or Sets preference of the Ui\Diff object
     *
     * @param string|array $prefs  a key name or key-value pair(s)
     * @param mixed $value         value used when the first args is string
     * @return array|$this
     */
    public function preference($prefs = null, $value = null)
    {
        // set
        if (is_string($prefs) && isset($value)) {
            $this->preference[$prefs] = $value;
            return $this;
        } elseif (is_array($prefs)) {
            foreach ($prefs as $name => $value) {
                $this->preference[$name] = $value;
            }
            return $this;
        }
        // get
        return $this->preference;
    }

    /**
     * Handle requested revision(s)
     *
     * @return bool Whether to continue processing
     */
    protected function handle()
    {
        global $INPUT;

        // check diff with another page/media
        $id2 = $INPUT->str('id2');
        if ($this->id === $id2)
            $id2 = null;

        $rev = $rev2 = 0;
        // difflink icon click, eg. ?rev=123456789&do=diff
        if ($INPUT->has('rev'))
            $rev = $INPUT->int('rev');
        if ($INPUT->has('rev2')) {
            // submit button with two checked boxes
            $rev12 = $INPUT->arr('rev2', []);
            if (count($rev12) > 1) {
                $rev = $rev12[0];
                $rev2 = $rev12[1];
            }
            else 
                $rev2 = $INPUT->int('rev2');
        }
        $name = urldecode($INPUT->str('name'));
        $name2 = urldecode($INPUT->str('name2'));
        if (!$id2) {
            $id2 = $this->id;
            // no revision was given
            // compare previous to current
            if (!$rev && !$rev2) {
                $rev = -1;
            }
            else if ($rev2 !== 0 &&
                     $rev > $rev2 ||
                     $rev === 0) {
                [$rev, $rev2] = [$rev2, $rev];
                [$name, $name2] = [$name2, $name];
            }
            // else if ($rev === $rev2) {
            //     msg('Revisions are same');
            //     return false;
            // }
        }

        $this->item1 = $this->createItem($this->id, $rev);
        $this->item2 = $this->createItem($id2, $rev2);
        if (!$this->item1->validateCanRead() ||
            !$this->item2->validateCanRead()) {
            return false;
        }
        $this->item1->info[DiffItem::keyOfName] = $name;
        $this->item2->info[DiffItem::keyOfName] = $name2;

        return true;
    }
}
