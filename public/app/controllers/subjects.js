
angular.module('katapi.subjects', ['ngResource', 'katapi.api'])

.factory('Subject', ['$resource', function ($resource) {
    return $resource('/subjects/:id.json', {id: '@id'}, {
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

.factory('Subjects', ['$q', 'Subject', function ($q, Subject) {

    return {
        get: function(items) {
            var deferred = $q.defer(),
                waitingFor = items.length,
                subjects = [];

            function done() {
                waitingFor--;
                if (!waitingFor) {
                    deferred.resolve(subjects);
                } else {
                    console.log('Waiting for ' + waitingFor +  ' subjects to load');
                }
            }

            function onSuccess(doc) {
                subjects.push(doc);
                done();
            }

            function onFail(err) {
                var msg = 'Load failed';
                if (err.data && err.data.error && err.data.error.message) {
                    msg = err.data.error.message;
                }
                subjects.push({error: msg});
                done();
            }

            console.log('Waiting for ' + waitingFor +  ' subjects to load');
            items.forEach(function(item) {
                Subject.get(item, onSuccess, onFail);
            });

            return deferred.promise;
        }
    };

}])

.controller('SubjectsController', ['$scope', '$location', 'LocalApi', function($scope, $location, LocalApi) {
    console.log('[SubjectsController] Hello');

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
            console.log('[SubjectsController] Server returned ' + results.subjects.length + ' results');
            // console.log(results);
            Array.prototype.push.apply($scope.subjects, results.subjects);
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

    $scope.subjects = [];
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

.controller('SubjectController', ['$scope', 'subjects', function($scope, subjects) {
    console.log('Hello from SubjectController');
    console.log('We got ' + subjects.length + ' subjects');

    /*

     $subjectsAndClasses = [];

     foreach ($doc->subjects as $subj) {

     if (count($subjectsAndClasses) == 0 || $subjectsAndClasses[count($subjectsAndClasses) - 1]['code'] != $subj['vocabulary']) {
     $subjectsAndClasses[] = array(
     'code' => $subj['vocabulary'],
     'name' => array_get(Subject::$vocabularies, $subj['vocabulary'], $subj['vocabulary']),
     'items' => array(),
     );
     }

     $el = array(
     'url' => URL::to($subj['uri']),
     'term' => isset($subj['indexTerm']) ? $subj['indexTerm'] : 'n/a',
     'extras' => array(),
     );

     $subjectsAndClasses[count($subjectsAndClasses) - 1]['items'][] = $el;
     }
     */

    // Bygges ut etterhvert. Autoritativ liste finnes på
    // http://www.bibsys.no/files/pdf/andre_dokumenter/relator_codes.pdf

    $scope.roles = {
        en: {
            adp: 'adapter',
            aut: 'author',
            aui: 'author of introduction',
            aft: 'author of afterword, colophon, etc.',
            bjd: 'bookjacket designer',
            det: 'dedicatee',
            dgg: 'degree grantor',
            dub: 'dubious author',
            edt: 'editor',
            ill: 'illustrator',
            ivr: 'interviewer',
            ive: 'interviewee',
            pbd: 'publishing director',
            pbl: 'publisher',
            prt: 'printer',
            trl: 'translator'
        },
        nb: {
            adp: 'bearbeider',
            aut: 'forfatter',
            aui: 'forfatter av forord',
            aft: 'forfatter av etterord',
            bjd: 'omslagsdesigner',
            det: 'person tilegnet',
            dgg: 'eksamenssted',
            dub: 'usikkert forfatterskap',
            edt: 'redaktør',
            ill: 'illustratør',
            ivr: 'intervjuer',
            ive: 'intervjuobjekt',
            pbd: 'forlagsredaktør',
            pbl: 'forlag/utgiver',
            prt: 'trykker',
            trl: 'oversetter'
        }
    };

    // http://www.loc.gov/standards/sourcelist/subject.html
    // http://www.loc.gov/standards/sourcelist/classification.html
    $scope.vocabularies = {
        'noubomn': 'Realfagstermer',
        'humord': 'Humord',
        'tekord': 'Tekord',
        'ordnok': 'Ordnøkkelen',
        'lcsh': 'Library of Congress Subject Headings',
        'mesh': 'MeSH',
        'psychit': 'APA Thesaurus of psychological index terms',
        'acmccs': 'CCS',
        'ddc': 'DDC',
        'no-ureal-ca': 'Astrofysisk hylleoppstilling',
        'no-ureal-cb': 'Biologisk hylleoppstilling',
        'no-ureal-cg': 'Geofysisk hylleoppstilling',
        'inspec': 'INSPEC',
        'msc': 'MSC',
        'nlm': 'NLM-klassifikasjon',
        'oosk': 'UBB-klassifikasjon',
        'udc': 'UDC',
        'utk': 'UBO-klassifikasjon',
        'NO-TrBIB': 'Omtalt'
    };

    // http://www.loc.gov/standards/sourcelist/descriptive-conventions.html
    $scope.catalogingRules = {
        'katreg': 'Katalogiseringsregler: Anglo-American cataloguing rules, second edition /oversatt og bearbiedet for Norske forhold ved Inger Cathrine Spangen (Oslo: Nasjonalbiblioteket)',
        'rda': 'Resource Description and Access (Chicago, IL: American Library Association)',
    };

    $scope.subjects = subjects;

    if (subjects.length == 1) {
        $scope.subject = subjects[0];
    }

}]);
