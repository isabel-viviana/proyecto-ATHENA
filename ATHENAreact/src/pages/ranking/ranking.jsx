import "./ranking.css";

const departmentRanking = [
    { position: 1, name: "Bogotá D.C.", score: 309, change: "+12" },
    { position: 2, name: "Antioquia", score: 302, change: "+8" },
    { position: 3, name: "Valle del Cauca", score: 298, change: "+6" },
    { position: 4, name: "Cundinamarca", score: 292, change: "+4" },
    { position: 5, name: "Atlántico", score: 289, change: "+5" },
    { position: 6, name: "Santander", score: 284, change: "+3" },
    { position: 7, name: "Boyacá", score: 281, change: "+2" },
    { position: 8, name: "Nariño", score: 278, change: "+1" },
    { position: 9, name: "Caldas", score: 275, change: "+0" },
    { position: 10, name: "Tolima", score: 272, change: "+0" },
];

const nationalRanking = [
    { position: 1, name: "Marcos Lopez", score: 320, region: "Nacional" },
    { position: 2, name: "Angela Diaz", score: 315, region: "Antioquia" },
    { position: 3, name: "Carlos Mendoza", score: 311, region: "Bogotá D.C." },
    {
        position: 4,
        name: "Ana Rodriguez",
        score: 308,
        region: "Valle del Cauca",
    },
    { position: 5, name: "Luis Fernandez", score: 304, region: "Santander" },
];

const Ranking = () => {
    return (
        <div className="ranking-page">
            <section className="page-header">
                <div className="page-header__info">
                    <h1>Ranking ICFES</h1>
                    <p>
                        Consulta los mejores puntajes por departamento y el top
                        nacional del examen ICFES. Este ranking muestra los
                        resultados más recientes y el avance de las regiones con
                        mejor desempeño.
                    </p>
                </div>
            </section>

            <div className="ranking-grid">
                <article className="ranking-card">
                    <span className="ranking-card__label">
                        Mejor departamento
                    </span>
                    <div className="ranking-card__value">Bogotá D.C.</div>
                    <p className="ranking-card__meta">
                        Puntaje promedio ICFES de 309 +12 en la última
                        evaluación.
                    </p>
                </article>

                <article className="ranking-card">
                    <span className="ranking-card__label">
                        Puntaje nacional top
                    </span>
                    <div className="ranking-card__value">320 pts</div>
                    <p className="ranking-card__meta">
                        Mejor puntaje reportado por un estudiante en el ranking
                        nacional.
                    </p>
                </article>

                <article className="ranking-card">
                    <span className="ranking-card__label">
                        Promedio nacional
                    </span>
                    <div className="ranking-card__value">276 pts</div>
                    <p className="ranking-card__meta">
                        Promedio nacional aproximado calculado a partir de los
                        resultados de las regiones top.
                    </p>
                </article>
            </div>

            <div className="ranking-table-wrapper">
                <section className="table-card">
                    <h3>Ranking por departamento</h3>
                    <p>
                        Los 10 departamentos con mayor puntaje promedio ICFES.
                    </p>
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Departamento</th>
                                <th>Puntaje</th>
                                <th>Variación</th>
                            </tr>
                        </thead>
                        <tbody>
                            {departmentRanking.map((item) => (
                                <tr key={item.position}>
                                    <td className="rank-number">
                                        {item.position}
                                    </td>
                                    <td className="department-name">
                                        {item.name}
                                    </td>
                                    <td>
                                        <span className="score-chip">
                                            {item.score}
                                        </span>
                                    </td>
                                    <td>{item.change}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>

                <section className="national-panel">
                    <article className="national-card">
                        <div className="national-card__title">Top Nacional</div>
                        <div className="national-card__number">320</div>
                        <p className="national-card__subtitle">
                            Puntaje más alto registrado en el ranking nacional
                            ICFES.
                        </p>
                    </article>

                    <article className="top-department">
                        <div className="top-department__rank">
                            #1 Departamento en rendimiento
                        </div>
                        <div className="top-department__name">Bogotá D.C.</div>
                        <div className="top-department__meta">
                            Promedio: 309 pts • +12 respecto a la última
                            publicación.
                        </div>
                    </article>

                    <article className="table-card">
                        <h3>Top nacional estudiantes</h3>
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Estudiante</th>
                                    <th>Departamento</th>
                                    <th>Puntaje</th>
                                </tr>
                            </thead>
                            <tbody>
                                {nationalRanking.map((item) => (
                                    <tr key={item.position}>
                                        <td className="rank-number">
                                            {item.position}
                                        </td>
                                        <td className="department-name">
                                            {item.name}
                                        </td>
                                        <td>{item.region}</td>
                                        <td>
                                            <span className="score-chip">
                                                {item.score}
                                            </span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </article>
                </section>
            </div>
        </div>
    );
};

export default Ranking;
