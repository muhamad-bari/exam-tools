window.AppUtils = {
    toQuery(params) {
        const search = new URLSearchParams();
        Object.keys(params || {}).forEach((key) => {
            const value = params[key];
            if (value !== null && value !== undefined && value !== '') {
                search.set(key, value);
            }
        });
        return search.toString();
    },

    apiUrl(api, action, extra = {}) {
        const query = this.toQuery({ api, action, ...extra });
        return `index.php?${query}`;
    },

    routeUrl(route, extra = {}) {
        const query = this.toQuery({ route, ...extra });
        return `index.php?${query}`;
    }
};
