<body x-init="measurements = await (await fetch('/measurements')).json();loading = false"
      x-data="global" class="static"
      style="margin: 0">
<div style="width:500px" class="ml-10 mt-10 z-50 top-0 left-0 absolute rounded-md shadow-md">
    <div class="bg-white border-b p-4 font-semibold rounded-t-md"
         x-text="$store.showMeasurement === null ? 'Measurements' : 'Recording ' + $store.showMeasurement.recording_id"></div>
    <div x-show="!showUploader && $store.showMeasurement === null" style="height: 40vh" class="bg-white">
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
    <template x-if="$store.showMeasurement !== null">
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
            <template x-if="$store.showMeasurement.has_positions">
                <button class="bg-green-700 text-white rounded px-4 py-2 hover:bg-green-800"
                        @click="plotRoute($store.showMeasurement.recording_id)">Plot route
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
        <button @click="showUploader=true" x-show="!showUploader && $store.showMeasurement === null"
                class="bg-gray-700 text-white rounded px-4 py-2 hover:bg-gray-800">Upload Samples
        </button>
        <button @click="$store.showMeasurement=null" x-show="$store.showMeasurement !== null"
                class="bg-gray-700 text-white rounded px-4 py-2 hover:bg-gray-800">Back to list
        </button>
    </div>
</div>
<template x-if="$store.analyzing != null">
    <div class="bg-white z-50 bottom-0 left-0 right-0 absolute rounded-t shadow-md p-4">
        <div>
            <div class="max-h-32">
                <canvas id="analyzingcanvas"></canvas>
            </div>
        </div>
    </div>
</template>
