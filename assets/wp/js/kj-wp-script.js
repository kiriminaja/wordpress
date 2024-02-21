function ajaxRouteGenerator(){
    let url = `${window.location.origin}/wp-admin/admin-ajax.php`;
    if (url.includes('localhost') && !url.includes('localhost:')){
        url = `${window.location.origin}`
        let urlSplit = url.split("/wp-admin/");
        url = urlSplit[0]
        url += '/wp-admin/admin-ajax.php'
    }
    return url
}
