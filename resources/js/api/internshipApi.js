import api from './axios';

export function fetchAll(filters={}) {
    return api.get('/internships', { params: filters });
}

export function fetchOne(id) {
    return api.get(`/internships/${id}`);
}
