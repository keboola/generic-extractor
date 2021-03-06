#!/usr/bin/env perl

#
# Copyright 2015 Ryan Whitworth (rwhitworth)
# Distributed under the MIT License.
# See LICENSE or http://opensource.org/licenses/MIT
#

use Mojolicious::Lite;
use Digest::MD5;
use Bytes::Random::Secure qw( random_string_from );

@ARGV = qw(daemon --listen http://*:80);

get '/' => sub {
  my $c = shift;
  $c->render(template => 'index');
};

get '/ip' => sub {
  my $c = shift;
  $c->res->headers->header("Content-Type" => 'application/json');
  $c->res->headers->header("Access-Control-Allow-Origin" => '*');
  $c->render(text => '{"ip": "' . $c->tx->remote_address . '"}');
};

get '/headers' => sub {
  my $c = shift;
  my $text = "{\n";
  foreach my $x (keys %{$c->tx->req->headers->{headers}})
  {
    if (length($text) > 2) { $text .= ",\n"; }
    $text .= '"' . $x . '": "' . ${$c->tx->req->content->headers}{headers}{$x}[0] . '"';
  }
  $text .= "\n}";
  $c->res->headers->header("Content-Type" => 'application/json');
  $c->res->headers->header("Access-Control-Allow-Origin" => '*');
  $c->render(text => $text);
};

get '/date' => sub {
  my $c = shift;
  my $text = "{\n";
  my $time_t = time;
  my ($sec,$min,$hour,$mday,$mon,$year,$wday,$yday,$isdst) = localtime($time_t);
  $year += 1900;
  my $timestr = sprintf("%02d:%02d:%02d ", $hour, $min, $sec);
  if ($hour >= 13) { $timestr .= "PM"; }
  else { $timestr .= "AM"; }
  my $datestr = sprintf("%02d-%02d-%04d", $mon, $mday, $year);

  $text .= '"time": "' . $timestr . '",' . "\n";
  $text .= '"milliseconds_since_epoch": "' . ($time_t * 1000) . '",' . "\n";
  $text .= '"date": "' . $datestr . '"';
  $text .= "\n}";
  $c->res->headers->header("Content-Type" => 'application/json');
  $c->res->headers->header("Access-Control-Allow-Origin" => '*');
  $c->render(text => $text);
};

get '/random' => sub {
  my $c = shift;
  my $text = "{\"data\": \"";
  $text .= random_string_from(
      join( '', ( 'a' .. 'z' ), ( 'A' .. 'Z' ), ( '0' .. '9' ) ),
      10000
  );
  $text .= "\"\n}";
  $c->render(text => $text);
};

get '/http-code-503' => sub {
  my $c = shift;
  $c->render(text => 'server error', status => '503');
};

get '/echo' => sub {
  my $c = shift;
  $c->render(text => 'not defined');
};

get '/validate' => sub {
  my $c = shift;
  $c->render(text => 'not defined');
};

get '/cookie' => sub {
  my $c = shift;
  my $time_t = time * 1000;
  $c->cookie(time => $time_t);
  $c->res->headers->header("Content-Type" => 'application/json');
  $c->res->headers->header("Access-Control-Allow-Origin" => '*');
  $c->render(text => '{"time": "' . $time_t . '"}');
};

app->secrets(['passphrase1']);
app->start;

__DATA__

@@ index.html.ep
% layout 'default';
% title 'Welcome';
<h1>Welcome to the Mojolicious real-time web framework!</h1>
To learn more, you can browse through the documentation
<%= link_to 'here' => '/perldoc' %>.

@@ layouts/default.html.ep
<!DOCTYPE html>
<html>
  <head><title><%= title %></title></head>
  <body><%= content %></body>
</html>

@@ not_found.html.ep
<!DOCTYPE html>
<html>
  <head><title>Page not found</title></head>
  <body>Page not found <%= $status %></body>
</html>
