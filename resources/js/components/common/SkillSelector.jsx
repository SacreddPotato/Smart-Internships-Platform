export default function SkillSelector({skills, selectedIds, onChange}) {
    function toggleSkill(id) {
        onChange(
            selectedIds.includes(id) ? selectedIds.filter((sId) => sId !== id)
            : [...selectedIds, id]
        );
    }

    return (
        <div className='skill-toggle-list'>
            {skills.map((skill) => (
                <label
                    className={`skill-toggle ${selectedIds.includes(skill.id) ? 'skill-toggle-active' : ''}`}
                    key={skill.id}
                >
                    <input
                        className='skill-toggle-input'
                        type='checkbox'
                        checked={selectedIds.includes(skill.id)}
                        onChange={() => toggleSkill(skill.id)}
                    />
                    <span>{skill.name}</span>
                </label>
            ))}
        </div>
    );
}
