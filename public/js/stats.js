/*stats.js graphiques stat admin, données depuis MongoDBService via AdminController, affichées ici via Chart.js*/

document.addEventListener('DOMContentLoaded', function () {

    //recup donnés depuis data_attributes, pr pas js inline dans template//
    const statsData  = document.getElementById('stats-data');
    const dataMois   = JSON.parse(statsData.dataset.mois  || '[]');
    const dataMenus  = JSON.parse(statsData.dataset.menus || '[]');
    
    //graphique commande/mois//
    //formatage labels : "2026-1"->"Jan 2026"
    const moisNoms = ['Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'];
    const labelsMois = dataMois.map(d => {
        const m = d._id?.mois ?? 1;
        const a = d._id?.annee ?? 2026;
        return moisNoms[m - 1] + ' ' + a;
    });
    const valuesMois = dataMois.map(d => parseInt(d.nb ?? 0));

    new Chart(document.getElementById('chartMois').getContext('2d'), {
        type: 'bar',
        data: {
            labels: labelsMois,
            datasets: [{
                label: 'Commandes',
                data: valuesMois,
                backgroundColor: '#5C7A5C', // var(--vert)
                borderRadius: 4,
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });

    //graphique menu +commandés//
    const labelsMenus = dataMenus.map(d => d.titre ?? 'Menu');
    const valuesMenus = dataMenus.map(d => parseInt(d.nb_commandes ?? 0));

    new Chart(document.getElementById('chartMenus').getContext('2d'), {
        type: 'bar',
        data: {
            labels: labelsMenus,
            datasets: [{
                label: 'Commandes',
                data: valuesMenus,
                backgroundColor: '#C4693A', // var(--terre)
                borderRadius: 4,
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
            indexAxis: 'y', // Graphique horizontal — meilleure lisibilité
        }
    });

});