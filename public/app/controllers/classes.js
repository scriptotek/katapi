
angular.module('katapi.classes', ['ngResource', 'katapi.api'])

.factory('Classification', ['$resource', function ($resource) {
    return $resource('/classes/:id.json', {id: '@id'}, {
        get: {
            method: 'GET',
            cache: true,
            transformResponse: function(data, headers){
                //MESS WITH THE DATA
                data = JSON.parse(data);
                return data;
            }
        }
    });
}])

.factory('Classes', ['$q', 'Classification', function ($q, Classification) {

    return {
        get: function(items) {
            var deferred = $q.defer(),
                waitingFor = items.length,
                classes = [];

            function done() {
                waitingFor--;
                if (!waitingFor) {
                    deferred.resolve(classes);
                } else {
                    console.log('Waiting for ' + waitingFor +  ' classes to load');
                }
            }

            function onSuccess(doc) {
                classes.push(doc);
                done();
            }

            function onFail(err) {
                var msg = 'Load failed';
                if (err.data && err.data.error && err.data.error.message) {
                    msg = err.data.error.message;
                }
                classes.push({error: msg});
                done();
            }

            console.log('Waiting for ' + waitingFor +  ' classes to load');
            items.forEach(function(item) {
                Classification.get(item, onSuccess, onFail);
            });

            return deferred.promise;
        }
    };

}])

.controller('ClassesController', ['$scope', '$location', 'LocalApi', function($scope, $location, LocalApi) {
    console.log('[ClassesController] Hello');

    $scope.query = $location.search().q;

    $scope.show = {
        indexing: false,
        series: false,
        notes: false,
        holdings: false
    };

    showFromString($location.search().show ? $location.search().show : 'indexing,series,notes');

    function getSearch(query, nextRecordPosition) {

        if (!query || query.length === 0) {
            return;
        }
        $scope.busy = true;
        LocalApi.search(query, nextRecordPosition).then(function(results) {
            console.log('[ClassesController] Server returned ' + results.classes.length + ' results');
            // console.log(results);
            Array.prototype.push.apply($scope.classes, results.classes);
            $scope.numberOfRecords = results.numberOfRecords;
            $scope.nextRecordPosition = results.nextRecordPosition;
            $scope.busy = false;
        }, function(error) {
            $scope.error = error ? error : 'Søket gikk ut i feil';
            $scope.busy = false;
        });
    }

    function showToString() {
        var s = [];
        for (var key in $scope.show) {
            if ($scope.show.hasOwnProperty(key) && $scope.show[key]) {
                s.push(key);
            }
        }
        return s.join(',');
    }

    function showFromString(s) {
        s = s.split(',');
        for (var key in $scope.show) {
            if ($scope.show.hasOwnProperty(key)) {
                $scope.show[key] = (s.indexOf(key) != -1);
            }
        }
    }

    $scope.search = function() {
        $location.path('/documents').search({
            'q': $scope.query,
            'continue': $scope.nextRecordPosition,
            'show': showToString()
        });
    };

    $scope.classes = [];
    $scope.numberOfRecords = 0;
    $scope.nextRecordPosition = 1;

    //getSearch($scope.query, $scope.nextRecordPosition);

    $scope.moreResults = function() {
        if ($scope.nextRecordPosition) {
            console.log('[DocumentsController] Fetch more results');
            getSearch($scope.query, $scope.nextRecordPosition);
        } else {
            console.log('[DocumentsController] Reached end of list');
        }
    };

}])

.controller('ClassController', ['$scope', 'classes', function($scope, classes) {
    console.log('Hello from ClassController');
    console.log('We got ' + classes.length + ' classes');

    $scope.classes = classes;

    if (classes.length == 1) {
        $scope.classification = classes[0];
    }

}]);
