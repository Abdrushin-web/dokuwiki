<?php

namespace dokuwiki\Ui;

use dokuwiki\ChangeLog\RevisionInfo;
use dokuwiki\Form\Form;
use InlineDiffFormatter;
use TableDiffFormatter;

/**
 * DokuWiki PageDiff Interface
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @author Satoshi Sahara <sahara.satoshi@gmail.com>
 * @package dokuwiki\Ui
 */
class PageDiff extends Diff
{
    /**
     * PageDiff Ui constructor
     *
     * @param string $id  page id
     */
    public function __construct($id = null)
    {
        global $INFO;
        if (!isset($id)) $id = $INFO['id'];

        // init preference
        $this->preference['showIntro'] = true;
        $this->preference['difftype'] = 'sidebyside'; // diff view type: inline or sidebyside

        parent::__construct($id);
    }

    /** @inheritdoc */
    protected function createItem(string $id, $rev = 0) : DiffItem
    {
        return new PageDiffItem($id, $rev);
    }

    /**
     * Set text to be compared with most current version
     * when it has been externally edited
     * exclusively use of the compare($old, $new) method
     *
     * @param string $text
     * @return $this
     */
    public function compareWith($text = null)
    {
        global $lang;

        if (isset($text)) {
            // revision info of older file (left side)
            $this->item1 = $this->createItem($this->id);
            $this->item1->info += [
                'navTitle' => $this->revisionTitle($this->item1),
                PageDiffItem::keyOfText => rawWiki($this->id),
            ];

            // revision info of newer file (right side)
            $this->item2 = $this->createItem($this->id, false);
            $this->item2->info += [
                'date' => null,
              //'ip'   => '127.0.0.1',
              //'type' => DOKU_CHANGE_TYPE_CREATE,
                'id'   => $this->id,
              //'user' => '',
              //'sum'  => '',
              //'extra' => '',
                'sizechange' => strlen($this->text) - io_getSizeFile(wikiFN($this->id)),
                DiffItem::keyOfTimestamp => false,
                'navTitle' => $lang['yours'],
                'originalText' => $text,
                PageDiffItem::keyOfText => cleanText($text),
            ];
        }
        return $this;
    }

    /**
     * Handle requested revision(s) and diff view preferences
     *
     * @return bool Whether to continue processing
     */
    protected function handle()
    {
        // requested rev or rev2
        if (!parent::handle()) {
            return false;
        }

        global $INPUT;

        // requested diff view type
        if ($INPUT->has('difftype')) {
            $this->preference['difftype'] = $INPUT->str('difftype');
        } else {
            // read preference from DokuWiki cookie. PageDiff only
            $mode = get_doku_pref('difftype', null);
            if (isset($mode)) $this->preference['difftype'] = $mode;
        }

        if (!$INPUT->has('rev') && !$INPUT->has('rev2')) {
            global $INFO, $REV;
            if ($this->id == $INFO['id'])
                $REV = $this->item1->rev; // store revision back in $REV
        }

        return true;
    }

    /**
     * Prepare revision info of comparison pair
     */
    protected function preProcess()
    {
        global $lang;

        // check validity of items
        foreach ([&$this->item1, &$this->item2] as $item) {
            if (!$item->rev) {
                // invalid revision number, set dummy revInfo
                $item->info += [
                    'date' => time(),
                    'type' => '',
                    'timestamp' => false,
                    'rev'  => false,
                    PageDiffItem::keyOfText => '',
                    'navTitle' => '&mdash;',
                ];
            }
        }
        if ($this->item2->rev === false) {
            msg(sprintf($lang['page_nonexist_rev'],
                $this->id,
                wl($this->id, ['do'=>'edit']),
                $this->id), -1);
        }

        foreach ([&$this->item1, &$this->item2] as $item) {
            // use timestamp and '' properly as $rev for the current file
            $isCurrent = $item->isCurrent();
            $item->info += [
                'rev'     => $isCurrent ? '' : $item->date()
            ];

            // headline in the Diff view navigation
            if (!isset($item->info['navTitle'])) {
                $item->info['navTitle'] = $this->revisionTitle($item);
            }

            if ($item->info['type'] == DOKU_CHANGE_TYPE_DELETE) {
                //attic stores complete last page version for a deleted page
                $item->info[PageDiffItem::keyOfText] = '';
            } else {
                $item->info[PageDiffItem::keyOfText] = rawWiki($item->id, $item->info['rev']);
            }
        }
    }

    /**
     * Show diff
     * between current page version and provided $text
     * or between the revisions provided via GET or POST
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     *
     * @return void
     */
    public function show()
    {
        if (!isset($this->item1, $this->item2)) {
            // retrieve form parameters: rev, rev2, difftype
            if (!$this->handle())
                return;
            // prepare revision info of comparison pair, except PageConflict or PageDraft
            $this->preProcess();
        }

        // create difference engine object
        $Difference = new \Diff(
                explode("\n", $this->item1->info[PageDiffItem::keyOfText]),
                explode("\n", $this->item2->info[PageDiffItem::keyOfText])
        );

        // build paired navigation
        [$navOlderRevisions, $navNewerRevisions] = $this->buildRevisionsNavigation();

        // display intro
        if ($this->preference['showIntro']) echo p_locale_xhtml('diff');

        // print form to choose diff view type, and exact url reference to the view
        if ($this->item2->rev !== false) {
            $this->showDiffViewSelector();
        }

        // assign minor edit checker to the variable
        $classEditType = function ($info) {
            return ($info['type'] === DOKU_CHANGE_TYPE_MINOR_EDIT) ? ' class="minor"' : '';
        };

        // display diff view table
        echo '<div class="table">';
        echo '<table class="diff diff_'.$this->diffType() .'">';

        //navigation and header
        switch ($this->diffType()) {
            case 'inline':
                if ($this->newRevInfo['rev'] !== false) {
                    echo '<tr>'
                        .'<td class="diff-lineheader">-</td>'
                        .'<td class="diffnav">'. $navOlderRevisions .'</td>'
                        .'</tr>';
                    echo '<tr>'
                        .'<th class="diff-lineheader">-</th>'
                        .'<th'.$classEditType($this->item1->info).'>'.$this->item1->info['navTitle'].'</th>'
                        .'</tr>';
                }
                echo '<tr>'
                    .'<td class="diff-lineheader">+</td>'
                    .'<td class="diffnav">'. $navNewerRevisions .'</td>'
                    .'</tr>';
                echo '<tr>'
                    .'<th class="diff-lineheader">+</th>'
                    .'<th'.$classEditType($this->item2->info).'>'.$this->item2->info['navTitle'].'</th>'
                    .'</tr>';
                // create formatter object
                $DiffFormatter = new InlineDiffFormatter();
                break;

            case 'sidebyside':
            default:
                if ($this->item2->rev !== false) {
                    echo '<tr>'
                        .'<td colspan="2" class="diffnav">'. $navOlderRevisions .'</td>'
                        .'<td colspan="2" class="diffnav">'. $navNewerRevisions .'</td>'
                        .'</tr>';
                }
                echo '<tr>'
                    .'<th colspan="2"'.$classEditType($this->item1->info).'>'.$this->item1->info['navTitle'].'</th>'
                    .'<th colspan="2"'.$classEditType($this->item2->info).'>'.$this->item2->info['navTitle'].'</th>'
                    .'</tr>';
                // create formatter object
                $DiffFormatter = new TableDiffFormatter();
                break;
        }

        // output formatted difference
        echo $this->insertSoftbreaks($DiffFormatter->format($Difference));

        echo '</table>';
        echo '</div>';
    }

    /**
     * Revision Title for PageDiff table headline
     *
     * @param DiffItem $item  Page item
     * @return string
     */
    protected function 
    
    revisionTitle(DiffItem &$item)
    {
        global $lang;

        // revision info may have timestamp key when external edits occurred
        $hasTimestamp = $item->hasTimestamp();

        $date = $item->date();
        if (isset($date)) {
            $rev = $date;
            if ($hasTimestamp === false) {
                // externally deleted or older file restored
                $title = '<bdi><a class="wikilink2" href="' . wl($item->id) . '">'
                   . $item->name() . '</a></bdi>';
            } else {
                $title = '<bdi><a class="wikilink1" href="' . wl($item->id, ['rev' => $rev]) . '">'
                   . $item->name() . '</a> | ' . dformat($rev) . '</bdi>';
            }
        } else {
            $title = '&mdash;';
        }
        if ($item->isCurrent()) {
            $title .= '&nbsp;|&nbsp;' . $lang['current'];
        }

        // append separator
        $title .= ($this->diffType() === 'inline') ? ' ' : '<br />';

        // supplement
        if (isset($date)) {
            $RevInfo = new RevisionInfo($item->info);
            $title .= $RevInfo->editSummary().' '.$RevInfo->editor();
        }
        return $title;
    }

    /**
     * Print form to choose diff view type, and exact url reference to the view
     */
    protected function showDiffViewSelector()
    {
        global $lang;

        // use timestamp for current revision
        [$oldRev, $newRev] = [(int)$this->item1->date(), (int)$this->item2->date()];

        echo '<div class="diffoptions group">';

        // create the form to select difftype
        $form = new Form(['action' => wl()]);
        $form->setHiddenField('id', $this->id);
        $form->setHiddenField('rev2[0]', $oldRev);
        $form->setHiddenField('rev2[1]', $newRev);
        $form->setHiddenField('id2', $this->item2->id);
        $form->setHiddenField('name', urlencode($this->item1->name()));
        $form->setHiddenField('name2', urlencode($this->item2->name()));
        $form->setHiddenField('do', 'diff');
        $options = array(
                     'sidebyside' => $lang['diff_side'],
                     'inline' => $lang['diff_inline'],
        );
        $input = $form->addDropdown('difftype', $options, $lang['diff_type'])
            ->val($this->diffType())
            ->addClass('quickselect');
        $input->useInput(false); // inhibit prefillInput() during toHTML() process
        $form->addButton('do[diff]', 'Go')->attr('type','submit');
        echo $form->toHTML();

        // show exact url reference to the view when it is meaningful
        echo '<p>';
        if ($oldRev && $newRev) {
            // link to exactly this view FS#2835
            $viewUrl = $this->isDiffWithAnotherPage() ?
                $this->diffWithAnotherPageViewLinks() :
                $this->diffViewlink('difflink', $oldRev, $newRev);
        }
        echo $viewUrl ?? '<br />';
        echo '</p>';

        echo '</div>'; // .diffoptions
    }

    public function isDiffWithAnotherPage() : bool
    {
        return $this->item1->id !== $this->item2->id;
    }

    /**
     * Create html for revision navigation
     *
     * The navigation consists of older and newer revisions selectors, each
     * state mutually depends on the selected revision of opposite side.
     *
     * @return string[] html of navigation for both older and newer sides
     */
    protected function buildRevisionsNavigation()
    {
        if ($this->item2->rev === false ||
            $this->isDiffWithAnotherPage()) {
            // no revisions selector for PageConflict or PageDraft or different pages
            return array('', '');
        }

        $changelog = &$this->item1->changeLog;

        // use timestamp for current revision
        [$oldRev, $newRev] = [(int)$this->item1->date(), (int)$this->item2->date()];

        // retrieve revisions with additional info
        [$oldRevs, $newRevs] = $changelog->getRevisionsAround($oldRev, $newRev);

        // build options for dropdown selector
        $olderRevisions = $this->buildRevisionOptions('older', $oldRevs);
        $newerRevisions = $this->buildRevisionOptions('newer', $newRevs);

        // determine previous/next revisions
        $index = array_search($oldRev, $oldRevs);
        $oldPrevRev = ($index +1 < count($oldRevs)) ? $oldRevs[$index +1] : false;
        $oldNextRev = ($index > 0)                  ? $oldRevs[$index -1] : false;
        $index = array_search($newRev, $newRevs);
        $newPrevRev = ($index +1 < count($newRevs)) ? $newRevs[$index +1] : false;
        $newNextRev = ($index > 0)                  ? $newRevs[$index -1] : false;

        /*
         * navigation UI for older revisions / Left side:
         */
        $navOlderRevs = '';
        // move backward both side: ◀◀
        if ($oldPrevRev && $newPrevRev)
            $navOlderRevs .= $this->diffViewlink('diffbothprevrev', $oldPrevRev, $newPrevRev);
        // move backward left side: ◀
        if ($oldPrevRev)
            $navOlderRevs .= $this->diffViewlink('diffprevrev', $oldPrevRev, $newRev);
        // dropdown
        $navOlderRevs .= $this->buildDropdownSelector('older', $olderRevisions);
        // move forward left side: ▶
        if ($oldNextRev && ($oldNextRev < $newRev))
            $navOlderRevs .= $this->diffViewlink('diffnextrev', $oldNextRev, $newRev);

        /*
         * navigation UI for newer revisions / Right side:
         */
        $navNewerRevs = '';
        // move backward right side: ◀
        if ($newPrevRev && ($oldRev < $newPrevRev))
            $navNewerRevs .= $this->diffViewlink('diffprevrev', $oldRev, $newPrevRev);
        // dropdown
        $navNewerRevs .= $this->buildDropdownSelector('newer', $newerRevisions);
        // move forward right side: ▶
        if ($newNextRev) {
            if ($changelog->isCurrentRevision($newNextRev)) {
                $navNewerRevs .= $this->diffViewlink('difflastrev', $oldRev, $newNextRev);
            } else {
                $navNewerRevs .= $this->diffViewlink('diffnextrev', $oldRev, $newNextRev);
            }
        }
        // move forward both side: ▶▶
        if ($oldNextRev && $newNextRev)
            $navNewerRevs .= $this->diffViewlink('diffbothnextrev', $oldNextRev, $newNextRev);

        return array($navOlderRevs, $navNewerRevs);
    }

    /**
     * prepare options for dropdwon selector
     *
     * @params string $side  "older" or "newer"
     * @params array $revs  list of revisions
     * @return array
     */
    protected function buildRevisionOptions($side, $revs)
    {
        $changelog = &$this->item1->changeLog;
        $revisions = array();

        // use timestamp for current revision
        [$oldRev, $newRev] = [(int)$this->item1->date(), (int)$this->item2->date()];

        foreach ($revs as $rev) {
            $info = $changelog->getRevisionInfo($rev);
            // revision info may have timestamp key when external edits occurred
            $info['timestamp'] = $info['timestamp'] ?? true;
            $date = dformat($info['date']);
            if ($info['timestamp'] === false) {
                // exteranlly deleted or older file restored
                $date = preg_replace('/[0-9a-zA-Z]/','_', $date);
            }
            $revisions[$rev] = array(
                'label' => implode(' ', [
                            $date,
                            editorinfo($info['user'], true),
                            $info['sum'],
                           ]),
                'attrs' => ['title' => $rev],
            );
            if (($side == 'older' && ($newRev && $rev >= $newRev))
              ||($side == 'newer' && ($rev <= $oldRev))
            ) {
                $revisions[$rev]['attrs']['disabled'] = 'disabled';
            }
        }
        return $revisions;
    }

    /**
     * build Dropdown form for revisions navigation
     *
     * @params string $side  "older" or "newer"
     * @params array $options  dropdown options
     * @return string
     */
    protected function buildDropdownSelector($side, $options)
    {
        $form = new Form(['action' => wl($this->id)]);
        $form->setHiddenField('id', $this->id);
        $form->setHiddenField('do', 'diff');
        $form->setHiddenField('difftype', $this->diffType());

        // use timestamp for current revision
        [$oldRev, $newRev] = [(int)$this->item1->date(), (int)$this->item2->date()];

        switch ($side) {
            case 'older': // left side
                $form->setHiddenField('rev2[1]', $newRev);
                $input = $form->addDropdown('rev2[0]', $options)
                    ->val($oldRev)->addClass('quickselect');
                $input->useInput(false); // inhibit prefillInput() during toHTML() process
                break;
            case 'newer': // right side
                $form->setHiddenField('rev2[0]', $oldRev);
                $input = $form->addDropdown('rev2[1]', $options)
                    ->val($newRev)->addClass('quickselect');
                $input->useInput(false); // inhibit prefillInput() during toHTML() process
                break;
        }
        $form->addButton('do[diff]', 'Go')->attr('type','submit');
        return $form->toHTML();
    }

    /**
     * Create html link to a diff view defined by two revisions
     *
     * @param string $linktype
     * @param int $oldRev older revision
     * @param int $newRev newer revision or null for diff with current revision
     * @return string html of link to a diff view
     */
    protected function diffViewlink($linktype, $oldRev, $newRev = null)
    {
        global $lang;
        if ($newRev === null) {
            $urlparam = array(
                'rev' => $oldRev
            );
        } else {
            $urlparam = array(
                'rev2[0]' => $oldRev,
                'rev2[1]' => $newRev
            );
        }
        return $this->diffViewLinkBase($linktype, $urlparam);
    }

    function diffType()
    {
        return $this->preference['difftype'];
    }
    
    protected function diffViewLinkBase(string $linktype, array &$urlparam) : string
    {
        return self::diffViewLinkBaseStatic($this->id, $this->diffType(), $linktype, $urlparam);
    }

    protected static function diffViewLinkBaseStatic(string $id, string $difftype, string $linktype, array &$urlparam, string $content = '') : string
    {
        global $lang;
        $urlparam['do'] = 'diff';
        $urlparam['difftype'] = $difftype;
        $title = $lang[$linktype];
        if (!$content)
            $content = '<span>' . $title . '</span>';
        $attr = array(
            'class' => $linktype,
            'href'  => wl($id, $urlparam, true, '&'),
            'title' => $title
        );
        return '<a ' . buildAttributes($attr) . '>' . $content . "</a>\n";
    }

    static function diffWithAnotherPageViewLinkStatic(
        string $id,
        string $id2,
        string $name,
        string $name2,
        string $difftype = '',
        bool $swap = false,
        string $content = ''
        ) : string
    {
        if ($swap)
        {
            $id1 = $id2;
            $urlparam =
            [
                'name' => urlencode($name2),
                'id2' => $id,
                'name2' => urlencode($name)
            ];
            $linktype = 'difflinkswap';
        }
        else
        {
            $id1 = $id;
            $urlparam =
            [
                'name' => urlencode($name),
                'id2' => $id2,
                'name2' => urlencode($name2)
            ];
            $linktype = 'difflink';
        }
        return self::diffViewLinkBaseStatic($id1, $difftype, $linktype, $urlparam, $content);
    }

    function diffWithAnotherPageViewLink(bool $swap = false) : string
    {
        return self::diffWithAnotherPageViewLinkStatic($this->item1->id, $this->item2->id, $this->item1->name(), $this->item2->name(), $this->diffType(), $swap);
    }

    function diffWithAnotherPageViewLinks() : string
    {
        return $this->diffWithAnotherPageViewLink(true) . ' | ' . $this->diffWithAnotherPageViewLink();
    }


    /**
     * Insert soft breaks in diff html
     *
     * @param string $diffhtml
     * @return string
     */
    public function insertSoftbreaks($diffhtml)
    {
        // search the diff html string for both:
        // - html tags, so these can be ignored
        // - long strings of characters without breaking characters
        return preg_replace_callback('/<[^>]*>|[^<> ]{12,}/', function ($match) {
            // if match is an html tag, return it intact
            if ($match[0][0] == '<') return $match[0];
            // its a long string without a breaking character,
            // make certain characters into breaking characters by inserting a
            // word break opportunity (<wbr> tag) in front of them.
            $regex = <<< REGEX
(?(?=              # start a conditional expression with a positive look ahead ...
&\#?\\w{1,6};)     # ... for html entities - we don't want to split them (ok to catch some invalid combinations)
&\#?\\w{1,6};      # yes pattern - a quicker match for the html entity, since we know we have one
|
[?/,&\#;:]         # no pattern - any other group of 'special' characters to insert a breaking character after
)+                 # end conditional expression
REGEX;
            return preg_replace('<'.$regex.'>xu', '\0<wbr>', $match[0]);
        }, $diffhtml);
    }

}
