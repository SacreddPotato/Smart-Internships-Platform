import api from './axios';

export function apply(internshipId, payload = {}) {
    return api.post(`/internships/${internshipId}/applications`, payload);
}

export function fetchMine(params = {}) {
    return api.get('/student/applications', { params });
}

export function fetchForCompany(params = {}) {
    return api.get('/company/applications', { params });
}

export function fetchOne(id) {
    return api.get(`/applications/${id}`);
}
