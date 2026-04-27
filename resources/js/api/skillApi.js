import api from './axios';

export function fetchAll() {
    return api.get('/skills');
}
