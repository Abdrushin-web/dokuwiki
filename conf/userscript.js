var breadcrumbs, sticky, stickyYOffset, trace;

window.addEventListener(
    'DOMContentLoaded',
    (event) =>
    {
        breadcrumbs = document.getElementsByClassName("breadcrumbs")[0];
        sticky = breadcrumbs.getElementsByClassName("youarehere")[0];
        trace = breadcrumbs.getElementsByClassName("trace")[0];
        stickyYOffset = sticky.offsetTop;
    });

window.onscroll = () =>
{
    if (breadcrumbs === undefined)
        return;
    const stickyClass = "sticky";
    var classes = sticky.classList;
    if (window.pageYOffset >= stickyYOffset)
        classes.add(stickyClass);
    else
        classes.remove(stickyClass);
};


/** Show each section link when hovering over each respective headline.
 * Select that link text by mouse click on the headline.
 *
 * @author Anika Henke <anika@selfthinker.org>
 * @author Sancaya (http://zen-do.ru/write)
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 *
 * See https://www.dokuwiki.org/tips:copy_section_link
 */
 
function selectLink(event)
{
    // header is clicked - select the content of the span at its end
    var headerChildren = this.children;
    var target = headerChildren[headerChildren.length - 1]; // = last child
    var rng, sel;                    // select its text
    if ( document.createRange ) {
        rng = document.createRange();
        rng.selectNode( target );
        sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange( rng );
    } else {
        var rng = document.body.createTextRange();
        rng.moveToElementText( target );
        rng.select();
    }
}
 
function addWikiLinksToHeadlines() {
    var heads = jQuery('.page :not(div.ProseMirror) h1, .page :not(div.ProseMirror) h2, .page :not(div.ProseMirror) h3, .page :not(div.ProseMirror) h4, .page :not(div.ProseMirror) h5');
    heads.click(selectLink);	// bind selection on mouse click
    var anchorId;
    heads.each(function(){
        $this = jQuery(this);
        anchorId = this.id;     // (and if "id" belongs not to the header
        if (!anchorId) anchorId = this.childNodes[0].id;  // - but to its 1st child)
        var wikiLink = '[[:'+JSINFO.id+'#'+anchorId+'|'+$this.text()+']]';
        $this.append(jQuery('<span class="wikiLink">'+wikiLink+'</span>'));
    });
}
 
//jQuery(addWikiLinksToHeadlines);