<!DOCTYPE html>
<html lang="en" ng-app="katapi">
<head>
  <meta charset="utf-8" />
  <title>KatAPI</title>

  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />

  <!-- Latest compiled and minified CSS -->
  <link rel="stylesheet" href="//netdna.bootstrapcdn.com/bootstrap/3.1.1/css/bootstrap.min.css">

  <!-- Optional theme -->
  <link rel="stylesheet" href="//netdna.bootstrapcdn.com/bootstrap/3.1.1/css/bootstrap-theme.min.css">

  <!-- Font Awesome -->
  <link href="//netdna.bootstrapcdn.com/font-awesome/4.0.3/css/font-awesome.css" rel="stylesheet">

  <!-- Open Sans -->
  <link href='http://fonts.googleapis.com/css?family=Open+Sans&amp;subset=latin,latin-ext' rel='stylesheet' type='text/css'>

  <link href="{{ URL::to('app.css') }}" rel="stylesheet">

  <script src="https://ajax.googleapis.com/ajax/libs/angularjs/1.2.25/angular.min.js"></script>
  <script src="{{ URL::to('app.js') }}"></script>

</head>
<body>

  <div class="container" ng-controller="MainCtrl">
    <h1>
      <i class="fa fa-cogs" style="color: #ddd;"></i>
      <a href="{{ URL::to('/') }}">katapi</a>
    </h1>
    @yield('content')
  </div>

</body> 
</html>

