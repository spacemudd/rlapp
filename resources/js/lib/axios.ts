import axios from 'axios';

// Configure axios to automatically handle CSRF tokens
// Laravel uses XSRF-TOKEN cookie and expects X-XSRF-TOKEN header
axios.defaults.withCredentials = true;
axios.defaults.withXSRFToken = true;

// Set base URL if needed
axios.defaults.baseURL = window.location.origin;

// Add request interceptor for additional headers if needed
axios.interceptors.request.use(
    (config) => {
        // Axios will automatically handle XSRF-TOKEN cookie
        // and add X-XSRF-TOKEN header for POST, PUT, PATCH, DELETE requests
        return config;
    },
    (error) => {
        return Promise.reject(error);
    }
);

export default axios;

