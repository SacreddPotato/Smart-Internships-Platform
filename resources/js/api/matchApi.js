import api from './axios';

export function fetchScore(internshipId) {
    return api.get(`/internships/${internshipId}/match-score`);
}

export function fetchRecommendations(params = {}) {
    return api.get(`/student/recommendations`, { params });
}
