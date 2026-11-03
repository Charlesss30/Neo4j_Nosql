WITH RECURSIVE ancestors(id) AS (
    SELECT parentId
    FROM parent_of
    WHERE childId = :id
    UNION
    SELECT edge.parentId
    FROM parent_of AS edge
    JOIN ancestors AS known ON edge.childId = known.id
)
SELECT p.*
FROM person AS p
JOIN ancestors AS a ON a.id = p.id
ORDER BY p.id;
