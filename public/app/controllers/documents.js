
angular.module('katapi.documents', ['ngResource', 'katapi.api', 'katapi.hyphenateISBN'])

.factory('DocumentUtils', ['ISBN', function (ISBN) {

  // Source: http://www.shamasis.net/2009/09/fast-algorithm-to-find-unique-items-in-javascript-array/
  var unique = function(a) {
      var o = {}, i, l = a.length, r = [];
      for(i=0; i<l;i+=1) o[a[i]] = a[i];
      for(i in o) r.push(o[i]);
      return r;
  };

  return {

    postprocess: function(doc) {
      // console.log('[DocumentUtils] Postprocessing document');
      var q = {};
      if (doc.subjects) {
        doc.subjects.forEach(function(subj) {
          if (!q[subj.vocabulary]) q[subj.vocabulary] = [];
          q[subj.vocabulary].push({
            url: subj.uri,
            term: subj.indexTerm,
            extras: []
          });
        });
      }
      if (doc.classes) {
        doc.classes.forEach(function(subj) {
          if (!q[subj.system]) q[subj.system] = [];
          q[subj.system].push({
            url: subj.uri,
            term: subj.number,
            edition: subj.edition,
            extras: []
          });
        });
      }
      if (doc.isbns) {
        doc.isbns = unique(doc.isbns.map(ISBN.hyphenate));
      }
      doc.subjectsAndClasses = q;

      return doc;
    }
  };

}])

.factory('Document', ['$resource', 'DocumentUtils', function ($resource, DocumentUtils) {
  return $resource('/documents/show/:id.json', {id: '@id'}, {
    get: {
      method: 'GET',
      cache: true,
      transformResponse: function(data, headers){
        //MESS WITH THE DATA
        data = JSON.parse(data);
        return DocumentUtils.postprocess(data);
      }
    }
  });
}])


.factory('Documents', function ($q, Document) {

  return {
    get: function(ids) {
      var deferred = $q.defer(),
          waitingFor = ids.length,
          docs = [];

      function done() {
        waitingFor--;
        if (!waitingFor) {
          deferred.resolve(docs);
        } else {
          console.log('Waiting for ' + waitingFor +  ' docs to load');
        }
      }

      function gotDocument(doc) {
        docs.push(doc);
        done();
      }

      function loadFailed(err) {
        var msg = 'Load failed';
        if (err.data && err.data.error && err.data.error.message) {
          msg = err.data.error.message;
        }
        docs.push({error: msg});
        done();
      }

      console.log('Waiting for ' + waitingFor +  ' docs to load');
      ids.forEach(function(id) {
        Document.get({ id: id }, gotDocument, loadFailed);
      });

      return deferred.promise;    
    }
  };

})

.controller('DocumentsController', ['$scope', '$location', 'LocalApi', function($scope, $location, LocalApi) {
  console.log('[DocumentsController] Hello');

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
      console.log('[DocumentsController] Server returned ' + results.documents.length + ' results');
      // console.log(results);
      Array.prototype.push.apply($scope.docs, results.documents);
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

  $scope.docs = [];
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

.controller('DocumentController', ['$scope', 'docs', function($scope, docs) {
  console.log('Hello from DocumentController');
  console.log('We got ' + docs.length + ' documents');

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
    aut: 'forfatter',
    aui: 'forfatter av forord',
    aft: 'forfatter av etterord',
    edt: 'redaktør',
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
  };

  // http://www.loc.gov/standards/sourcelist/descriptive-conventions.html
  $scope.catalogingRules = {
    'katreg': 'Katalogiseringsregler: Anglo-American cataloguing rules, second edition /oversatt og bearbiedet for Norske forhold ved Inger Cathrine Spangen (Oslo: Nasjonalbiblioteket)',
    'rda': 'Resource Description and Access (Chicago, IL: American Library Association)',
  };

  $scope.docs = docs;

  if (docs.length == 1) {
    $scope.doc = docs[0];
  }

}])

.directive('volumes', ['LocalApi', function (LocalApi) {
  return { 

    restrict : 'E',  // element names only
    templateUrl: '/app/templates/volumes.html',
    scope: {},  // isolate scope

    link: function(scope, element, attrs) {
      console.log('Linking <volumes>');
      //console.log(element);
      //console.log(attrs);

      function fetch(id) {
        console.log('Fetching volumes for: ' + id);
        LocalApi.search('bs.serieobjektid=' + id).then(function(results) {
          console.log(results);
          scope.volumes = results.documents;
        });
      }

      if (attrs.itemId) {
        fetch(attrs.itemId);
      } else {
        element.html('<em>No id provided</em>');
      }

    }
  };
}])


.directive('work', ['LocalApi', function (LocalApi) {
  return { 

    restrict : 'E', // element names only
    templateUrl: '/app/templates/work.html',
    scope: {
    //  bibsys_id: '=itemId'  // isolate scope
    },

    link: function(scope, element, attrs) {

      console.log('Linking <work>');
      console.log(element);
      console.log(attrs);

      function fetch(id, part) {
        scope.bibsys_id = id;
        console.log('Fetching work title');
        LocalApi.search('bs.objektid=' + id).then(function(results) {
          console.log(results);
          // console.log('setting ' + scope.title);
          scope.title = results.documents[0].title;
          scope.part = part;
        });
      }

      if (attrs.itemId) {
        fetch(attrs.itemId, attrs.itemPart);
      } else {
        element.html('<em>No id provided</em>');
      }

    }
  };
}])

.directive('library', ['LocalApi', function (LocalApi) {
  return {

    restrict : 'E',  // element names only
    scope: { id: '=id' },  // isolate scope
    templateUrl: '/app/templates/library.html',

    link: function(scope, element, attrs) {
      console.log('Linking <library>');
      //console.log(element);
      //console.log(attrs);

      function fetch(id) {
        console.log('Fetching library title');
        scope.id = id;
        scope.title = id;
        scope.busy = true;
        LocalApi.lookupLibrary(id).then(function(record) {
          // console.log(docs);
          scope.busy = false;
          console.log('setting title: ' + record.inst);
          scope.title = record.inst;
        });
      }

      if (attrs.id) {
        fetch(attrs.id);
      } else {
        element.html('<em>No id provided</em>');
      }

    }
  };
}])

.directive('document', [function () {
  return {

    restrict : 'E', // element names only
    templateUrl: '/app/templates/documents/show.html',
    scope: true, // Inherit prototypically

    link: function(scope, element, attrs) {
      console.log('Linking <document>: ' + attrs.itemIndex);
      if (attrs.itemIndex === undefined) {
        element.html('<em>No item-id provided</em>');
        return;
      }
      scope.doc = scope.docs[attrs.itemIndex];
    }
  };
}]);

//})(angular);