import InternshipCard from "./InternshipCard";

export default function InternshipList( { internships }) {
    if (internships.length === 0) {
        return (
            <div className='card-grid'>
                <h2 className='empty-state-title'>No internships found</h2>
                <p className='empty-state-copy'>Try adjusting your search or type filter</p>
            </div>
        )

    }

    return (
        <div className='card-grid'>
            {internships.map((internship) => <InternshipCard key={internship.id} internship={internship} />)}
        </div>
    )
}
