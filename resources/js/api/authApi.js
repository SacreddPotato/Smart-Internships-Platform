import api from './axios';

export const register = (userData) => {
  return api.post('/register', userData);
};

export const login = (credentials) => {
    return api.post('/login', credentials);
};

export const logout = () => {
    return api.post('/logout');
}

export const me = () => {
    return api.get('/me');
};
