<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <script src="https://cdn.tailwindcss.com"></script>
    <title>Railway Analysis</title>
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

<body x-init="measurements = await (await fetch('/measurements')).json();loading = false"
      x-data="global" class="static"
      style="margin: 0">
<div style="width:500px" class="ml-10 mt-10 z-50 top-0 left-0 absolute rounded-md shadow-md">
    <div class="bg-white border-b p-4 font-semibold rounded-t-md"
         x-text="$store.showMeasurement === -1 ? 'Measurements' : 'Recording ' + $store.showMeasurement.recording_id"></div>
    <div x-show="!showUploader && $store.showMeasurement === -1" style="height: 40vh" class="bg-white">
        <span class="p-4" x-show="loading">Loading measurements...</span>
        <template x-for="measurement in measurements">
            <div
                :class="'flex flex-col cursor-pointer py-6 px-4 border-b ' + ($store.analyzing == measurement ? 'bg-green-50 hover:bg-green-100' : 'hover:bg-gray-100')"
                @click="$store.showMeasurement = measurement"
            >
                <div class="flex justify-between">
                    <span x-text="'Recording ' + measurement.recording_id"></span>
                    <span class="font-light text-gray-700" x-text="measurement.samples + ' Samples'"></span>
                </div>
                <template x-if="!measurement.has_positions">
                    <div class="text-center text-red-800 mt-4">
                        <span>Positions Missing</span>
                    </div>
                </template>
            </div>
        </template>
    </div>
    <template x-if="$store.showMeasurement !== -1">
        <div style="height: 40vh" class="bg-white p-4">
            <h2 class="font-light text-xl">Recording details</h2>
            <div x-show="!$store.showMeasurement.has_positions">
                <p class="text-sm text-gray-700">The samples cannot be displayed, as no positions have been uploaded
                    yet. Please upload below.</p>

                <form class="mt-4 flex justify-between"
                      :action="'/measurements/' + $store.showMeasurement.recording_id + '/positions'"
                      method="post"
                      enctype="multipart/form-data">
                    @csrf
                    <input required name="file" type="file">
                    <button type="submit" class="bg-green-300 rounded p-1">Upload</button>
                </form>
            </div>
            <template x-if="$store.showMeasurement.has_positions && $store.analyzing == -1">
                <button class="bg-green-700 text-white rounded px-4 py-2 hover:bg-green-800"
                        @click="plotRoute($store.showMeasurement.recording_id)">Plot route
                </button>
            </template>
            <template x-if="$store.showMeasurement.has_positions && $store.analyzing != -1">
                <button class="bg-green-700 text-white rounded px-4 py-2 hover:bg-green-800"
                        @click="$store.currentJoint.add($store.showMeasurement.recording_id)">Compare
                </button>
            </template>
        </div>
    </template>
    <div class="bg-white p-4" style="height: 40vh" x-show="showUploader">
        <h2 class="font-light text-xl">Upload samples</h2>
        <form class="mt-4 flex justify-between" action="{{ route('upload') }}" method="post"
              enctype="multipart/form-data">
            @csrf
            <input name="file" type="file">
            <button type="submit" class="bg-green-300 rounded p-1">Upload</button>
        </form>
    </div>
    <div class="px-4 py-4 bg-gray-100 rounded-b-md border-t">
        <button @click="showUploader=false" x-show="showUploader"
                class="bg-gray-700 text-white rounded px-4 py-2 hover:bg-gray-800">Cancel
        </button>
        <button @click="showUploader=true" x-show="!showUploader && $store.showMeasurement === -1"
                class="bg-gray-700 text-white rounded px-4 py-2 hover:bg-gray-800">Upload Samples
        </button>
        <button @click="$store.showMeasurement=-1" x-show="$store.showMeasurement !== -1"
                class="bg-gray-700 text-white rounded px-4 py-2 hover:bg-gray-800">Back to list
        </button>
    </div>
</div>
<template x-if="$store.analyzing != -1">
    <div class="bg-white z-50 bottom-0 left-0 right-0 absolute rounded-t shadow-md p-4">
        <template x-for="recording in $store.currentJoint.comparing">
            <div class="flex items-center overflow-x-auto">
                <div class="flex flex-col mr-2 h-36 justify-between">
                    <span class="text-small font-semibold" x-text="'Recording ' + recording.id"></span>
                    <div>
                        <div class="flex gap-1">
                            <button
                                :class="'border px-3 py-1 rounded transition-colors' + (recording.plotting.includes('x') ?' bg-green-500 hover:bg-green-600 text-white' : ' text-black hover:bg-gray-100')">
                                X
                            </button>
                            <button
                                :class="'border px-3 py-1 rounded transition-colors' + (recording.plotting.includes('y') ?' bg-green-500 hover:bg-green-600 text-white' : ' text-black hover:bg-gray-100')">
                                Y
                            </button>
                            <button
                                :class="'border px-3 py-1 rounded transition-colors' + (recording.plotting.includes('z') ?' bg-green-500 hover:bg-green-600 text-white' : ' text-black hover:bg-gray-100')">
                                Z
                            </button>
                        </div>
                    </div>
                    <button class="bg-gray-100 border mt-2 rounded text-center w-full">
                        Normalize Z
                    </button>
                    <span x-text="recording.speed + ' km/t'" class="text-xs text-center font-light"></span>
                </div>
                <div style="min-width: 80vw" class="flex-1 flex items-center max-h-40">
                    <button class="border p-1 h-36" @click="previous"><</button>
                    <canvas :id="'chart-' + recording.id"></canvas>
                    <button class="border p-1 h-36" @click="next">></button>
                </div>
                <div style="min-width: 60vw" :class="max-h-40'">
                    <canvas :id="'fft-' + recording.id"></canvas>
                </div>
            </div>
        </template>
    </div>
</template>
<div id="map"></div>

<script type="module">

    (async () => {
        const tokenID = "{{ config('maps.map_token') }}";

        if (!window.mapkit || window.mapkit.loadedLibraries.length === 0) {
            // mapkit.core.js or the libraries are not loaded yet.
            // Set up the callback and wait for it to be called.
            await new Promise(resolve => {
                window.initMapKit = resolve
            });

            // Clean up
            delete window.initMapKit;
        }

        mapkit.init({
            authorizationCallback: function (done) {
                done(tokenID);
            }
        });
        mapkit.addEventListener("configuration-change", function (event) {
            switch (event.status) {
                case "Initialized":
                    console.log('initialized')
                    break;
                case "Refreshed":
                    // The MapKit JS configuration updates.
                    break;
            }
        });


        window.map = new mapkit.Map("map");

        let circleOverlay = [];

        let selectedCircle = null;
        map.addEventListener('region-change-end', async function (event) {
            if (selectedCircle != null)
                return;

            if (map.cameraDistance > 27000) {
                return;
            }
            map.removeOverlays(circleOverlay);
            circleOverlay = [];

            var style = new mapkit.Style({
                lineWidth: 3,         // 2 CSS pixels.
                strokeColor: "#0044ff",
                fillColor: "#0011ff",
            });
            let lat = map.center.latitude;
            let long = map.center.longitude;
            let joints = await (await fetch(`/api/joints?lat=${lat}&long=${long}`)).json();


            for (let joint of joints) {
                let pos = new mapkit.Coordinate(joint.coordinates.coordinates[1], joint.coordinates.coordinates[0]);
                let circle = new mapkit.CircleOverlay(pos, 6);
                circle.data = {
                    id: joint.id
                }
                circle.style = style;
                circle.addEventListener('select', (args) => {
                    if (Alpine.store('showMeasurement') === -1) {
                        alert("Please select a recording first.")
                        return;
                    }
                    if (selectedCircle != null) {
                        selectedCircle.style = new mapkit.Style({
                            lineWidth: 3,         // 2 CSS pixels.
                            strokeColor: "#0044ff",
                            fillColor: "#0011ff",
                        });
                    }
                    selectedCircle = args.target;
                    selectedCircle.style = new mapkit.Style({
                        lineWidth: 3,         // 2 CSS pixels.
                        strokeColor: "#ff4400",
                        fillColor: "#ff1100",
                    })
                    Alpine.store('analyzing', Alpine.store('showMeasurement'))
                    Alpine.store('currentJoint').loadId(selectedCircle.data.id);
                })
                circleOverlay.push(circle);
            }

            map.addOverlays(circleOverlay);


            // map.removeItems(map.annotions);
            //console.log(map.center, map.cameraDistance)
        });
        map.setCenterAnimated(new mapkit.Coordinate(55.930345, 10.785089), false);
        map.setCameraDistanceAnimated(600000)

    })();

</script>
<script>
    let lineoverlay = null;

    async function plotRoute(recordingId) {
        if (lineoverlay != null) {
            map.removeOverlay(lineoverlay);
            lineoverlay = null;
        }
        let positions = await ((await fetch(`/measurements/${recordingId}/positions`)).json())
        var style = new mapkit.Style({
            lineWidth: 4,
            lineJoin: "round",
            lineGradient: new mapkit.LineGradient({
                0: "#F0F",
                1: "#9f009f",
            }),
        });
        var coords = positions.map(p => new mapkit.Coordinate(p.lat, p.long))
        var polyline = new mapkit.PolylineOverlay(coords, {style: style});
        lineoverlay = polyline;
        window.map.addOverlay(polyline);
        map.setCenterAnimated(new mapkit.Coordinate(coords[0].latitude, coords[0].longitude), true);
        await new Promise(r => setTimeout(r, 500));
        map.setCameraDistanceAnimated(26000, true)

    }
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    let primaryChart = {};
    let fftChart = {};
    let gpsOverlay = {};

    async function loadChart(joint, recordingId) {
        if (recordingId in primaryChart)
            primaryChart[recordingId].destroy();
        if (recordingId in fftChart)
            fftChart[recordingId].destroy();
        if (recordingId in gpsOverlay)
            map.removeOverlay(gpsOverlay[recordingId]);

        let positions = await (await fetch(`/measurements/${recordingId}/closest?joint=${joint}`)).json()
        let data = await (await fetch(`https://railway.test/measurements/${recordingId}/data?t=${positions[0].time - 1200}&distance=1500`)).json()

        Alpine.store('currentJoint').comparing[recordingId].speed = Math.round(data.kmt)
        const ctx = document.getElementById('chart-' + recordingId);
        primaryChart[recordingId] = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.points.labels,
                datasets: [{
                    label: 'z',
                    data: data.points.z,
                    lineTension: 0.5,
                    borderWidth: 1,
                    pointRadius: 0
                }]
            },
            options: {
                plugins: {
                    legend: false,
                },
                scales: {},
                responsive: true,
                maintainAspectRatio: false,
            }
        });
        const ctx2 = document.getElementById('fft-' + recordingId);
        fftChart[recordingId] = new Chart(ctx2, {
            type: 'line',
            data: {
                labels: data.points.fourier.labels,
                datasets: [{
                    label: 'hz',
                    data: data.points.fourier.z,
                    borderWidth: 1,
                    pointRadius: 0
                }]
            },
            options: {
                plugins: {
                    legend: false,
                },
                scales: {},
                responsive: true,
                maintainAspectRatio: false,
            }
        });

        var style = new mapkit.Style({
            lineWidth: 4,
            lineJoin: "round",
            lineGradient: new mapkit.LineGradient({
                0: "#0F0",
                1: "#009f00",
            }),
        });
        var coords = data.points.coordinates.map(p => new mapkit.Coordinate(p.lat, p.long))
        var polyline = new mapkit.PolylineOverlay(coords, {style: style});
        console.log(polyline);

        map.setCenterAnimated(new mapkit.Coordinate(coords[0].latitude, coords[0].longitude), true);
        gpsOverlay[recordingId] = polyline;
        window.map.addOverlay(polyline);
    }

</script>
<script src="https://unpkg.com/alpinejs" defer></script>
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('global', () => ({
            showUploader: false,
            loading: true,
            measurements: [],
        }))

        Alpine.store('showMeasurement', -1);
        Alpine.store('analyzing', -1);
        Alpine.store('currentJoint', {
            comparing: {},
            id: -1,
            loadId(id) {
                this.id = id
                this.comparing = {};
                let recordingId = Alpine.store('analyzing').recording_id;
                this.add(recordingId);
            },
            add(id) {
                this.comparing[id] = {
                    plotting: ['z'],
                    id: id,
                    loading: false,
                    speed: "-",
                };
                loadChart(this.id, id)
            }
        });
    })
</script>
</body>
</html>
