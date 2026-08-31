(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.breboMailPager = {
    attach(context) {
      once('brebo-mail-pager-top', '.brebo-mail-list', context).forEach((list) => {
        const bottomPager = list.querySelector(':scope > .brebo-mail-pager');
        const pageInfo = list.querySelector(':scope > .brebo-mail-list__page-info');
        if (!bottomPager || !pageInfo) {
          return;
        }

        const topPager = bottomPager.cloneNode(true);
        topPager.classList.add('brebo-mail-pager--top');
        const topInfo = pageInfo.cloneNode(true);
        topInfo.classList.add('brebo-mail-list__page-info--top');

        const topBar = document.createElement('div');
        topBar.className = 'brebo-mail-page-navigation brebo-mail-page-navigation--top';
        topBar.append(topPager, topInfo);
        list.insertBefore(topBar, list.firstChild);

        const bottomBar = document.createElement('div');
        bottomBar.className = 'brebo-mail-page-navigation brebo-mail-page-navigation--bottom';
        bottomPager.parentNode.insertBefore(bottomBar, bottomPager);
        bottomBar.append(bottomPager, pageInfo);
      });
    }
  };
})(Drupal, once);
