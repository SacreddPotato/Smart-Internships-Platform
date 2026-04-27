import api from './axios';

export function fetchAll(filters={}) {
    return api.get('/internships', { params: filters });
}

export function fetchOne(id) {
    return api.get(`/internships/${id}`);
}

export function create(payload) {
    return api.post('/company/internships', payload);
}

export function update(id, payload) {
    return api.put(`/internships/${id}`, payload);
}

export function remove(id) {
    return api.delete(`/internships/${id}`);
}

export function archive(id) {
    return api.patch(`/internships/${id}/archive`);
}

export function fetchMine(params = {}) {
    return api.get('/company/internships', { params });
}

export function fetchArchived(params = {}) {
    return api.get('/company/internships/archived', { params });
}
