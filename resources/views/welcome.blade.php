<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">

    <script
        src="https://cdn.apple-mapkit.com/mk/5.x.x/mapkit.js"
        crossorigin async
        data-callback="initMapKit"
        data-libraries="map"
        data-initial-token="{{ config('maps.map_token') }}"
    ></script>

    <style>
        #map {
            width: 100%;
            height: 100vh;
        }
    </style>

</head>

<body style="margin: 0">
<div id="map"></div>

<script type="module">

    (async () => {
        const tokenID = {{ config('maps.map_token') }};

        if (!window.mapkit || window.mapkit.loadedLibraries.length === 0) {
            // mapkit.core.js or the libraries are not loaded yet.
            // Set up the callback and wait for it to be called.
            await new Promise(resolve => { window.initMapKit = resolve });

            // Clean up
            delete window.initMapKit;
        }

        mapkit.init({
            authorizationCallback: function(done) {
                done(tokenID);
            }
        });
        mapkit.addEventListener("configuration-change", function(event) {
            switch (event.status) {
                case "Initialized":
                    console.log('initialized')
                    break;
                case "Refreshed":
                    // The MapKit JS configuration updates.
                    break;
            }
        });



        const map = new mapkit.Map("map");
        map.addEventListener('region-change-end', async function(event) {
            if (map.cameraDistance > 27000) {
                return;
            }
            map.removeItems(map.annotations);

            let lat = map.center.latitude;
            let long = map.center.longitude;
            let joints = await (await fetch(`/api/joints?lat=${lat}&long=${long}`)).json();

            for (let joint of joints) {
                let annotation = new mapkit.MarkerAnnotation(new mapkit.Coordinate(joint.coordinates.coordinates[1], joint.coordinates.coordinates[0]), {
                    title: "Klæbesamling",
                    //clusteringIdentifier: "rail"
                })
                map.addItems(annotation);
            }


           // map.removeItems(map.annotions);
            //console.log(map.center, map.cameraDistance)
        });
        map.setCenterAnimated(new mapkit.Coordinate(55.930345, 10.785089), false);
        map.setCameraDistanceAnimated(600000)





        /*var sfo = new mapkit.Coordinate(37.616934, -122.383790),
            work = new mapkit.Coordinate(37.3349, -122.0090201);
        var sfoAnnotation = new mapkit.MarkerAnnotation(sfo, { color: "#f4a56d", title: "SFO", glyphText: "✈️" });

        // Setting properties after creation:
        var workAnnotation = new mapkit.MarkerAnnotation(work);
        workAnnotation.color = "#969696";
        workAnnotation.title = "Work";
        workAnnotation.subtitle = "Apple Park";
        workAnnotation.selected = "true";
        workAnnotation.glyphText = "";

        map.showItems([sfoAnnotation, workAnnotation]);*/

    })();

</script>
</body>
</html>
