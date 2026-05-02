export default function MatchScoreBadge({ score }) {
    if (score === null || score === undefined) {
        return null;
    }

    const tier = score>=80 ? 'high' : score>=50? 'medium' : 'low';

    return (
        <span className={`match-badge match-${tier}`}>
            {score}% match
        </span>
    );
}
