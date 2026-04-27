import { useState } from 'react';

export default function InternshipFilters({ filters, onChange }) {
    const [draftTerm, setDraftTerm] = useState('');

    function addTerm(event) {
        if (event.key !== 'Enter' || !draftTerm.trim()) {
            return;
        }

        event.preventDefault();
        const term = draftTerm.trim();

        onChange({
            ...filters,
            terms: [...filters.terms, term],
            page: 1,
        });

        setDraftTerm('');
    }

    function removeTerm(term) {
        onChange({
            ...filters,
            terms: filters.terms.filter((currentTerm) => currentTerm !== term),
            page: 1,
        });
    }

    function updateField(event) {
        onChange({
            ...filters,
            [event.target.name]: event.target.value,
            page: 1,
        });
    }

    return (
        <form className="filter-bar" onSubmit={(event) => event.preventDefault()}>
            <div className="search-token-box">
                <input
                    className="form-input"
                    type="search"
                    value={draftTerm}
                    onChange={(event) => setDraftTerm(event.target.value)}
                    onKeyDown={addTerm}
                    placeholder="Type a phrase and press Enter"
                />

                <div className="search-token-list">
                    {filters.terms.map((term) => (
                        <button
                            key={term}
                            type="button"
                            className="search-token"
                            onClick={() => removeTerm(term)}
                        >
                            {term} x
                        </button>
                    ))}
                </div>
            </div>

            <select
                className="form-select"
                name="match"
                value={filters.match}
                onChange={updateField}
            >
                <option value="any">Match any</option>
                <option value="all">Match all</option>
            </select>

            <select
                className="form-select"
                name="type"
                value={filters.type}
                onChange={updateField}
            >
                <option value="">All Types</option>
                <option value="remote">Remote</option>
                <option value="onsite">On-site</option>
                <option value="hybrid">Hybrid</option>
            </select>
        </form>
    );
}
