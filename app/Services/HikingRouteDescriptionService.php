<?php

namespace App\Services;

class HikingRouteDescriptionService
{
    private const DESCRIPTIONS = [
        'T' => [
            'it' => 'CARATTERISTICHE: Percorsi turistici su strade, mulattiere o larghi sentieri. Hanno un fondo lastricato o naturale ma sempre ben tracciato e segnalato. Si sviluppano nelle immediate vicinanze di paesi, località turistiche, vie di comunicazione e generalmente non superano i 2000 metri di quota. Sono escursioni facili e brevi che non richiedono particolare esperienza o preparazione fisica.',
            'en' => 'CHARACTERISTICS: Tourist routes on roads, mule tracks or wide paths. They have a paved or natural surface but are always well-marked and signposted. They develop in the immediate vicinity of villages, tourist locations, communication routes and generally do not exceed 2000 meters in altitude. These are easy and short excursions that do not require particular experience or physical preparation.',
            'es' => 'CARACTERÍSTICAS: Rutas turísticas por carreteras, caminos de mulas o senderos anchos. Tienen una superficie pavimentada o natural pero siempre están bien marcados y señalizados. Se desarrollan en las inmediaciones de pueblos, lugares turísticos, vías de comunicación y generalmente no superan los 2000 metros de altitud. Son excursiones fáciles y cortas que no requieren experiencia o preparación física particular.',
            'de' => 'EIGENSCHAFTEN: Touristische Routen auf Straßen, Maultierpfaden oder breiten Wegen. Sie haben einen gepflasterten oder natürlichen Untergrund, sind aber immer gut markiert und beschildert. Sie entwickeln sich in unmittelbarer Nähe von Dörfern, touristischen Orten, Verkehrswegen und übersteigen im Allgemeinen nicht 2000 Meter Höhe. Es sind leichte und kurze Ausflüge, die keine besondere Erfahrung oder körperliche Vorbereitung erfordern.',
            'fr' => 'CARACTÉRISTIQUES : Itinéraires touristiques sur routes, chemins muletiers ou larges sentiers. Ils ont une surface pavée ou naturelle mais sont toujours bien balisés et signalisés. Ils se développent à proximité immédiate des villages, des lieux touristiques, des voies de communication et ne dépassent généralement pas 2000 mètres d\'altitude. Ce sont des excursions faciles et courtes qui ne nécessitent pas d\'expérience ou de préparation physique particulière.',
            'pt' => 'CARACTERÍSTICAS: Rotas turísticas em estradas, caminhos de mulas ou trilhas largas. Têm superfície pavimentada ou natural, mas sempre bem marcada e sinalizada. Desenvolvem-se nas imediações de aldeias, locais turísticos, vias de comunicação e geralmente não ultrapassam os 2000 metros de altitude. São excursões fáceis e curtas que não requerem experiência ou preparação física particular.'
        ],
        'E' => [
            'it' => 'CARATTERISTICHE: Percorsi su sentieri o tracce in terreno vario (pascoli, detriti, pietraie). Sono generalmente segnalati con vernice o ometti (cairn). Possono svolgersi anche in ambienti innevati ma solo lievemente inclinati. Richiedono capacità di muoversi su terreni particolari, esperienza e conoscenza dell\'ambiente montano, allenamento alla camminata, oltre a calzature ed equipaggiamento adeguati.',
            'en' => 'CHARACTERISTICS: Routes on paths or tracks in varied terrain (pastures, debris, scree). They are generally marked with paint or cairns. They can also take place in snowy environments but only slightly inclined. They require the ability to move on particular terrain, experience and knowledge of the mountain environment, walking training, as well as adequate footwear and equipment.',
            'es' => 'CARACTERÍSTICAS: Rutas en senderos o pistas en terreno variado (pastos, escombros, pedreras). Generalmente están marcados con pintura o mojones. También pueden desarrollarse en entornos nevados pero solo ligeramente inclinados. Requieren capacidad para moverse en terrenos particulares, experiencia y conocimiento del entorno montañoso, entrenamiento para caminar, así como calzado y equipo adecuados.',
            'de' => 'EIGENSCHAFTEN: Routen auf Pfaden oder Wegen in abwechslungsreichem Gelände (Weiden, Geröll, Gestein). Sie sind in der Regel mit Farbe oder Steinmännchen markiert. Sie können auch in verschneiter Umgebung stattfinden, aber nur leicht geneigt. Sie erfordern die Fähigkeit, sich in bestimmtem Gelände zu bewegen, Erfahrung und Kenntnis der Bergumgebung, Wandertraining sowie angemessenes Schuhwerk und Ausrüstung.',
            'fr' => 'CARACTÉRISTIQUES : Itinéraires sur sentiers ou pistes en terrain varié (pâturages, débris, éboulis). Ils sont généralement marqués à la peinture ou par des cairns. Ils peuvent aussi se dérouler en milieu enneigé mais uniquement légèrement incliné. Ils nécessitent la capacité de se déplacer sur des terrains particuliers, de l\'expérience et la connaissance du milieu montagnard, de l\'entraînement à la marche, ainsi qu\'un équipement et des chaussures adaptés.',
            'pt' => 'CARACTERÍSTICAS: Rotas em trilhas ou caminhos em terreno variado (pastagens, detritos, pedregulhos). São geralmente marcados com tinta ou cairns. Também podem ocorrer em ambientes nevados, mas apenas ligeiramente inclinados. Requerem capacidade de se movimentar em terrenos particulares, experiência e conhecimento do ambiente montanhoso, treino de caminhada, bem como calçado e equipamento adequados.'
        ],
        'EE' => [
            'it' => 'CARATTERISTICHE: Percorsi generalmente segnalati ma che richiedono capacità di muoversi su terreni particolari. Sentieri o tracce su terreno impervio e infido (pendii ripidi e/o scivolosi di erba, o misti di rocce ed erba, o di roccia e detriti). Terreno vario, a quote relativamente elevate (di norma superiori ai 2000 metri). Possono presentarsi tratti rocciosi con lievi difficoltà tecniche e/o nevai. Necessitano di esperienza di montagna in generale e buona conoscenza dell\'ambiente alpino, passo sicuro e assenza di vertigini. Equipaggiamento, attrezzatura e preparazione fisica adeguate.',
            'en' => 'CHARACTERISTICS: Generally marked routes that require the ability to move on particular terrain. Paths or tracks on treacherous and uneven terrain (steep and/or slippery slopes of grass, or mixed rock and grass, or rock and debris). Varied terrain, at relatively high altitudes (normally above 2000 meters). There may be rocky sections with slight technical difficulties and/or snowfields. They require general mountain experience and good knowledge of the alpine environment, sure footing and absence of vertigo. Adequate equipment, gear and physical preparation.',
            'es' => 'CARACTERÍSTICAS: Rutas generalmente marcadas que requieren la capacidad de moverse en terrenos particulares. Senderos o pistas en terreno traicionero y desigual (pendientes empinadas y/o resbaladizas de hierba, o mixtas de roca y hierba, o de roca y escombros). Terreno variado, a altitudes relativamente altas (normalmente por encima de 2000 metros). Puede haber secciones rocosas con ligeras dificultades técnicas y/o campos de nieve. Requieren experiencia general en montaña y buen conocimiento del entorno alpino, paso seguro y ausencia de vértigo. Equipamiento, material y preparación física adecuados.',
            'de' => 'EIGENSCHAFTEN: Generell markierte Routen, die die Fähigkeit erfordern, sich in bestimmtem Gelände zu bewegen. Pfade oder Wege in tückischem und unebenem Gelände (steile und/oder rutschige Grashänge oder gemischte Fels- und Grashänge oder Fels und Geröll). Abwechslungsreiches Gelände in relativ großen Höhen (normalerweise über 2000 Meter). Es können felsige Abschnitte mit leichten technischen Schwierigkeiten und/oder Schneefelder vorhanden sein. Sie erfordern allgemeine Bergerfahrung und gute Kenntnis der alpinen Umgebung, sicheren Tritt und Schwindelfreiheit. Angemessene Ausrüstung, Ausrüstung und körperliche Vorbereitung.',
            'fr' => 'CARACTÉRISTIQUES : Itinéraires généralement balisés qui nécessitent la capacité de se déplacer sur des terrains particuliers. Sentiers ou pistes sur terrain traître et accidenté (pentes raides et/ou glissantes d\'herbe, ou mixtes de rochers et d\'herbe, ou de rochers et de débris). Terrain varié, à des altitudes relativement élevées (normalement au-dessus de 2000 mètres). Il peut y avoir des sections rocheuses avec de légères difficultés techniques et/ou des névés. Ils nécessitent une expérience générale de la montagne et une bonne connaissance du milieu alpin, un pied sûr et l\'absence de vertige. Équipement, matériel et préparation physique adéquats.',
            'pt' => 'CARACTERÍSTICAS: Rotas geralmente marcadas que requerem a capacidade de se movimentar em terrenos particulares. Trilhas ou caminhos em terreno traiçoeiro e irregular (encostas íngremes e/ou escorregadias de grama, ou mistas de rocha e grama, ou de rocha e detritos). Terreno variado, em altitudes relativamente elevadas (normalmente acima de 2000 metros). Pode haver seções rochosas com leves dificuldades técnicas e/ou campos de neve. Requerem experiência geral em montanha e bom conhecimento do ambiente alpino, passo seguro e ausência de vertigem. Equipamento, material e preparação física adequados.'
        ]
    ];

    private const ABSTRACT_TEMPLATES = [
        'point_to_point' => [
            'it' => 'Il percorso escursionistico :ref parte da :from nel comune di :city_from (:region_from) e arriva a :to nel comune di :city_to (:region_to). Il sentiero è classificato come :difficulty con una distanza totale di :distance km e un dislivello di :ascent m in salita e :descent m in discesa. Il tempo di percorrenza stimato è di :duration_forward ore in andata e :duration_backward ore al ritorno. L\'altitudine varia da un minimo di :ele_min m a un massimo di :ele_max m sul livello del mare.',
            'en' => 'The hiking trail :ref starts from :from in the municipality of :city_from (:region_from) and reaches :to in the municipality of :city_to (:region_to). The path is classified as :difficulty with a total distance of :distance km and an elevation gain of :ascent m uphill and :descent m downhill. The estimated walking time is :duration_forward hours outbound and :duration_backward hours return. The altitude varies from a minimum of :ele_min m to a maximum of :ele_max m above sea level.',
            'es' => 'El sendero :ref parte de :from en el municipio de :city_from (:region_from) y llega a :to en el municipio de :city_to (:region_to). El camino está clasificado como :difficulty con una distancia total de :distance km y un desnivel de :ascent m de subida y :descent m de bajada. El tiempo estimado de caminata es de :duration_forward horas de ida y :duration_backward horas de vuelta. La altitud varía desde un mínimo de :ele_min m hasta un máximo de :ele_max m sobre el nivel del mar.',
            'de' => 'Der Wanderweg :ref beginnt in :from in der Gemeinde :city_from (:region_from) und führt nach :to in der Gemeinde :city_to (:region_to). Der Weg ist als :difficulty klassifiziert mit einer Gesamtdistanz von :distance km und einem Höhenunterschied von :ascent m bergauf und :descent m bergab. Die geschätzte Gehzeit beträgt :duration_forward Stunden hin und :duration_backward Stunden zurück. Die Höhe variiert von minimal :ele_min m bis maximal :ele_max m über dem Meeresspiegel.',
            'fr' => 'Le sentier de randonnée :ref part de :from dans la commune de :city_from (:region_from) et arrive à :to dans la commune de :city_to (:region_to). Le sentier est classé comme :difficulty avec une distance totale de :distance km et un dénivelé de :ascent m en montée et :descent m en descente. Le temps de marche estimé est de :duration_forward heures à l\'aller et :duration_backward heures au retour. L\'altitude varie d\'un minimum de :ele_min m à un maximum de :ele_max m au-dessus du niveau de la mer.',
            'pt' => 'A trilha :ref parte de :from no município de :city_from (:region_from) e chega a :to no município de :city_to (:region_to). O caminho é classificado como :difficulty com uma distância total de :distance km e um desnível de :ascent m de subida e :descent m de descida. O tempo estimado de caminhada é de :duration_forward horas na ida e :duration_backward horas na volta. A altitude varia de um mínimo de :ele_min m a um máximo de :ele_max m acima do nível do mar.'
        ],
        'loop' => [
            'it' => 'Il percorso escursionistico ad anello :ref ha il suo punto di partenza e arrivo a :from nel comune di :city_from (:region_from). Il sentiero è classificato come :difficulty con una distanza totale di :distance km e un dislivello di :ascent m in salita e :descent m in discesa. Il tempo di percorrenza stimato è di :duration_forward ore. L\'altitudine varia da un minimo di :ele_min m a un massimo di :ele_max m sul livello del mare.',
            'en' => 'The circular hiking trail :ref has its starting and ending point at :from in the municipality of :city_from (:region_from). The path is classified as :difficulty with a total distance of :distance km and an elevation gain of :ascent m uphill and :descent m downhill. The estimated walking time is :duration_forward hours. The altitude varies from a minimum of :ele_min m to a maximum of :ele_max m above sea level.',
            'es' => 'El sendero circular :ref tiene su punto de inicio y final en :from en el municipio de :city_from (:region_from). El camino está clasificado como :difficulty con una distancia total de :distance km y un desnivel de :ascent m de subida y :descent m de bajada. El tiempo estimado de caminata es de :duration_forward horas. La altitud varía desde un mínimo de :ele_min m hasta un máximo de :ele_max m sobre el nivel del mar.',
            'de' => 'Der Rundwanderweg :ref hat seinen Start- und Endpunkt in :from in der Gemeinde :city_from (:region_from). Der Weg ist als :difficulty klassifiziert mit einer Gesamtdistanz von :distance km und einem Höhenunterschied von :ascent m bergauf und :descent m bergab. Die geschätzte Gehzeit beträgt :duration_forward Stunden. Die Höhe variiert von minimal :ele_min m bis maximal :ele_max m über dem Meeresspiegel.',
            'fr' => 'Le sentier de randonnée circulaire :ref a son point de départ et d\'arrivée à :from dans la commune de :city_from (:region_from). Le sentier est classé comme :difficulty avec une distance totale de :distance km et un dénivelé de :ascent m en montée et :descent m en descente. Le temps de marche estimé est de :duration_forward heures. L\'altitude varie d\'un minimum de :ele_min m à un maximum de :ele_max m au-dessus du niveau de la mer.',
            'pt' => 'A trilha circular :ref tem seu ponto de partida e chegada em :from no município de :city_from (:region_from). O caminho é classificado como :difficulty com uma distância total de :distance km e um desnível de :ascent m de subida e :descent m de descida. O tempo estimado de caminhada é de :duration_forward horas. A altitude varia de um mínimo de :ele_min m a um máximo de :ele_max m acima do nível do mar.'
        ]
    ];

    public function getCaiScaleDescription(string $scale): array
    {
        return self::DESCRIPTIONS[$scale] ?? [
            'it' => 'Difficoltà sconosciuta',
            'en' => 'Unknown difficulty',
            'de' => 'Unbekannte Schwierigkeit',
            'fr' => 'Difficulté inconnue',
            'es' => 'Dificultad desconocida',
            'pt' => 'Dificuldade desconhecida'
        ];
    }

    public function generateAbstract(array $data): array
    {
        $template = $data['roundtrip'] ?
            self::ABSTRACT_TEMPLATES['loop'] :
            self::ABSTRACT_TEMPLATES['point_to_point'];

        return array_map(function ($text) use ($data) {
            return $this->replacePlaceholders($text, $data);
        }, $template);
    }

    private function replacePlaceholders(string $text, array $data): string
    {
        $replacements = [
            ':ref' => $data['ref'],
            ':from' => $data['from']['from'],
            ':to' => $data['to']['to'] ?? '',
            ':city_from' => $data['from']['city_from'],
            ':city_to' => $data['to']['city_to'] ?? '',
            ':region_from' => $data['from']['region_from'],
            ':region_to' => $data['to']['region_to'] ?? '',
            ':difficulty' => $data['cai_scale'][$this->getCurrentLocale()] ?? 'Unknown',
            ':distance' => $data['tech']['distance'],
            ':ascent' => $data['tech']['ascent'],
            ':descent' => $data['tech']['descent'],
            ':duration_forward' => $data['tech']['duration_forward'],
            ':duration_backward' => $data['tech']['duration_backward'] ?? '',
            ':ele_min' => $data['tech']['ele_min'],
            ':ele_max' => $data['tech']['ele_max']
        ];

        return trim(preg_replace('/\s\s+/', ' ', strtr($text, $replacements)));
    }

    private function getCurrentLocale(): string
    {
        return app()->getLocale();
    }
}
