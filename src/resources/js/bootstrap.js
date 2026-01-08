import axios from "axios";
window.axios = axios;

window.axios.defaults.headers.common["X-Requested-With"] = "XMLHttpRequest";
window.axios.defaults.withCredentials = true;

// Set up CSRF token handling
const token = document.head.querySelector('meta[name="csrf-token"]');
if (token) {
    window.axios.defaults.headers.common["X-CSRF-TOKEN"] = token.content;
} else {
    // Fallback: try to get from cookie
    const getCookie = (name) => {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(";").shift();
    };
    const xsrfToken = getCookie("XSRF-TOKEN");
    if (xsrfToken) {
        window.axios.defaults.headers.common["X-XSRF-TOKEN"] =
            decodeURIComponent(xsrfToken);
    }
}
