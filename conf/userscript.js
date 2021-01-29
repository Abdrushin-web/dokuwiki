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