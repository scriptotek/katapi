
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

      console.log(doc);

      if (doc.subjects) {
        doc.subjects.forEach(function(subj) {
          if (!q[subj.vocabulary]) q[subj.vocabulary] = [];
          q[subj.vocabulary].push({
            type: 'subject',
            link: subj.link,
            term: subj.indexTerm,
            extras: []
          });
        });
      }
      if (doc.classifications) {
        var existing = [];
        doc.classifications.forEach(function(subj) {
          if (existing.indexOf(subj.link) != -1) return;  // skip dups
          existing.push(subj.link);
          if (!q[subj.system]) q[subj.system] = [];
          q[subj.system].push({
            type: 'class',
            link: subj.link,
            term: subj.number,
            edition: subj.edition,
            extras: []
          });
        });
      }
      if (doc.bibliographic && doc.bibliographic.isbns) {
        doc.bibliographic.isbns = unique(doc.bibliographic.isbns.map(ISBN.hyphenate));
      }
      doc.subjectsAndClasses = q;

      return doc;
    }
  };

}])

.factory('Document', ['$resource', 'DocumentUtils', function ($resource, DocumentUtils) {
  return $resource('/documents/:id.json', {id: '@id'}, {
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

.factory('Documents', ['$q', 'Document', 'LocalApi', function ($q, Document, LocalApi) {

  return {

    get: function(items) {
      var deferred = $q.defer(),
          waitingFor = items.length,
          docs = [];

      function done() {
        waitingFor--;
        if (!waitingFor) {
          deferred.resolve(docs);
        } else {
          console.log('Waiting for ' + waitingFor +  ' docs to load');
        }
      }

      function onSuccess(doc) {
        docs.push(doc);
        done();
      }

      function onFail(err) {
        var msg = 'Unknown error';
        if (err.data && err.data.error && err.data.error.message) {
          msg = err.data.error.code + ' ' + err.data.error.message;
        }
        docs.push({ error: msg });
        done();
      }

      console.log('Waiting for ' + waitingFor +  ' docs to load');
      items.forEach(function(item) {
        Document.get(item, onSuccess, onFail);
      });

      return deferred.promise;    
    },

    search: function(query, nextRecordPosition) {
      var deferred = $q.defer();
      LocalApi.search(query, nextRecordPosition).then(function(results) {
        deferred.resolve(results);
      }, function(error) {
        deferred.reject(error);
      });
      return deferred.promise;
    }

  };

}])


.factory('Reference', [function() {
  return {
    // http://www.loc.gov/standards/sourcelist/subject.html
    // http://www.loc.gov/standards/sourcelist/classification.html
    vocabularies: {
      null: 'Frie nøkkelord',
      'agrovoc': 'Agrovoc',
      'noubomn': 'Realfagstermer',
      'humord': 'Humord',
      'tekord': 'Tekord',
      'ordnok': 'Ordnøkkelen',
      'lcsh': 'LCSH',
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
    }
  };

}])

.controller('DocumentsController', ['$scope', '$location', 'Documents', 'Reference', function($scope, $location, Documents, Reference) {
  console.log('[DocumentsController] Hello');

  $scope.query = {};

  $scope.query.q = $location.search().q;

  $scope.vocabularies = Reference.vocabularies;

  $scope.query.show = { 
    sh: false,
    k: false,
    c: false,
    se: false,
    n: false,
    h: false
  };

  showFromString($location.search().show ? $location.search().show : 'sh');

  function getSearch(query, nextRecordPosition) {

    if (!query || query.length === 0) {
      return;
    }
    $scope.busy = true;
    Documents.search(query, nextRecordPosition).then(function(results) {
      console.log('[DocumentsController] Server returned ' + results.documents.length + ' results');
      // console.log(results);
      Array.prototype.unshift.apply(results.documents, $scope.results.documents);
      $scope.results = results;
      $scope.busy = false;
    }, function(error) {
      $scope.error = error ? error : 'Søket gikk ut i feil';
      $scope.busy = false;
    });
  }

  function showToString() {
    var s = [];
    for (var key in $scope.query.show) {
      if ($scope.query.show.hasOwnProperty(key) && $scope.query.show[key]) {
        s.push(key);
      }
    }
    return s.join(',');
  }

  function showFromString(s) {
    s = s.split(',');
    for (var key in $scope.query.show) {
      if ($scope.query.show.hasOwnProperty(key)) {
        $scope.query.show[key] = (s.indexOf(key) != -1);
      }
    }
  }

  $scope.search = function() {
    $location.path('/documents').search({
      'q': $scope.query.q,
      'continue': $scope.results.nextRecordPosition,
      'show': showToString()
    });
  };

  $scope.results = {
    numberOfRecords: -1,
    nextRecordPosition: 1,
    documents: []
  };

  //getSearch($scope.query, $scope.nextRecordPosition);

  $scope.moreResults = function() {
    if ($scope.results.nextRecordPosition) {
      console.log('[DocumentsController] Fetch more results');
      getSearch($scope.query.q, $scope.results.nextRecordPosition);
    } else {
      // console.log('[DocumentsController] Reached end of list');
    }
  };

}])

.controller('DocumentController', ['$scope', 'docs', 'Reference', function($scope, docs, Reference) {
  console.log('Hello from DocumentController');
  console.log('We got ' + docs.length + ' documents');

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

  $scope.vocabularies = Reference.vocabularies;

  // http://www.loc.gov/standards/sourcelist/descriptive-conventions.html
  $scope.catalogingRules = {
    'katreg': 'Katalogiseringsregler: Anglo-American cataloguing rules, second edition /oversatt og bearbiedet for Norske forhold ved Inger Cathrine Spangen (Oslo: Nasjonalbiblioteket)',
    'rda': 'Resource Description and Access (Chicago, IL: American Library Association)',
  };

  $scope.docs = docs;

  if (docs.length == 1) {
    $scope.doc = docs[0];
    $scope.biblio = docs[0].bibliographic; // convenience
    $scope.holdings = docs[0].holdings; // convenience
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

      function sorted(docs) {
        return docs.sort(function(a, b) {
          if (a.bibliographic.year == b.bibliographic.year) {
            if (a.bibliographic.part_no == b.bibliographic.part_no) {
              return 0;
            }
            return a.bibliographic.part_no > b.bibliographic.part_no ? 1 : -1;
          }
          return a.bibliographic.year > b.bibliographic.year ? 1 : -1;
        });
      }

      function fetch(id) {
        console.log('Fetching volumes for: ' + id);
        LocalApi.search('series:' + id).then(function(results) {
          console.log(results);
          scope.volumes = sorted(results.documents);
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
        LocalApi.search('bs.objektid=' + id).then(function(results) {
          console.log('Found work:');
          console.log(results);
          // console.log('setting ' + scope.title);
          scope.id = results.documents[0]._id;
          scope.title = results.documents[0].bibliographic.title;
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
      scope.biblio = scope.docs[attrs.itemIndex].bibliographic; // convenience
      scope.holdings = scope.docs[attrs.itemIndex].holdings; // convenience
      console.log(scope.doc);
    }
  };
}])

.directive('documentlist', ['LocalApi', function (LocalApi) {
  return { 

    restrict : 'E',  // element names only
    replace: true,
    templateUrl: '/app/templates/documentlist.html',
    scope: { docs: '=', show: '=' },

    link: function(scope, element, attrs) {
      console.log('Linking <documentlist>');
      // console.log(scope.show);
      // console.log(scope.docs);
    }
  };
}]);
