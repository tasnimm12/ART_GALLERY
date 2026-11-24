function wrapAdminIndexTable() {
    const tablePages = [
        {selector: '.ea-index-Artwork', className: 'admin-artwork-index'},
        {selector: '.ea-index-Notification', className: 'admin-notification-index'},
    ];
    const activePage = tablePages.find(({selector}) => document.body.matches(selector));

    if (!activePage) {
        return;
    }

    document.documentElement.classList.add(activePage.className);

    const table = document.querySelector('#main table.datagrid');
    if (!table || table.parentElement?.classList.contains('admin-table-wrapper')) {
        return;
    }

    const wrapper = document.createElement('div');
    wrapper.className = 'admin-table-wrapper';
    table.parentNode.insertBefore(wrapper, table);
    wrapper.appendChild(table);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', wrapAdminIndexTable, {once: true});
} else {
    wrapAdminIndexTable();
}
