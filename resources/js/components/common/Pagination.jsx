export default function Pagination( { meta, onPageChange }) {
    if (!meta || Number(meta.last_page) <= 1) {
        return null;
    }

    const currentPage = Number(meta.current_page);
    const lastPage = Number(meta.last_page);

    return (
        <nav className='pagination' aria-label='pagination'>
            <button
                type='button'
                className='btn btn-secondary'
                disabled={currentPage <= 1}
                onClick={() => onPageChange(currentPage - 1)}
            >
                Previous
            </button>
            <span className='pagination-status'>
                Page {currentPage} of {lastPage}
            </span>
            <button
                type='button'
                className='btn btn-secondary'
                disabled={currentPage >= lastPage}
                onClick={() => onPageChange(currentPage + 1)}
            >
                Next
            </button>
        </nav>
    );
}
