
angular.module('katapi.api', ['katapi.documents'])

.service('LocalApi', ['$http', '$q', 'DocumentUtils', function($http, $q, DocumentUtils) {


  this.get = function(id) {

    console.log('Get document: ' + id);

    var deferred = $q.defer();

    $http({
      url: '/documents/show/' + id + '.json',
      method: 'GET',
      params: {
      }
    })
    .error(function(response, status, headers, config) {
      deferred.reject(status);
    })
    .success(function(data) {
      deferred.resolve(data);
    });
  
    return deferred.promise;
  };

  this.search = function(query) {

    console.log('Searching for: ' + query);

    var deferred = $q.defer();

    $http({
      url: '/documents/search',
      method: 'GET',
      cache: true,
      params: {
        query: query,
        format: 'json'
      }
    })
    .error(function(response, status, headers, config) {
      deferred.reject(status);
    })
    .success(function(data) {

      if (data.error) {
        deferred.reject(data.error);
        return;
      }

      data.documents = data.documents.map(function(doc) {
        return DocumentUtils.postprocess(doc);
      });

      deferred.resolve(data.documents);
    });
  
    return deferred.promise;
  };

  this.lookupLibrary = function(id) {

    console.log('Searching for: ' + id);

    var deferred = $q.defer();

    $http({
      url: '/libraries/show/' + id,
      method: 'GET',
      params: {
        format: 'json'
      }
    })
    .error(function(response, status, headers, config) {
      deferred.reject(status);
    })
    .success(function(data) {
      deferred.resolve(data.record);
    });
  
    return deferred.promise;
  };

}])

;